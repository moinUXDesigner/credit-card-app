<?php

namespace App\Services;

use App\Http\Requests\StoreCardRequest;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class CardImportService
{
    private const BOOLEAN_FIELDS = ['lounge_access', 'is_active'];

    /**
     * Parse an uploaded CSV/Excel/JSON file of cards, validate each row with
     * the same rules as the single-card creation endpoint, and create a Card
     * per valid row for the given user. Invalid rows are skipped and reported
     * rather than aborting the whole import, so one typo doesn't block the rest.
     *
     * @return array{imported: int, failed: int, errors: array<int, array{row: int, errors: array<int, string>}>}
     */
    public function import(User $user, UploadedFile $file): array
    {
        $rows = $this->parseRows($file);

        $imported = 0;
        $errors = [];

        $groupLimits = Card::query()
            ->where('user_id', $user->id)
            ->whereNotNull('shared_limit_group')
            ->pluck('total_limit', 'shared_limit_group')
            ->map(fn ($limit) => (float) $limit)
            ->all();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $data = $this->normalizeRow($row);

            $validator = Validator::make($data, StoreCardRequest::fieldRules());

            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'errors' => $validator->errors()->all()];

                continue;
            }

            $validated = $validator->validated();
            $group = $validated['shared_limit_group'] ?? null;

            if ($group) {
                $limit = (float) $validated['total_limit'];
                if (isset($groupLimits[$group]) && $groupLimits[$group] !== $limit) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ["Cards sharing limit group \"{$group}\" must all use the same total_limit ({$groupLimits[$group]})."],
                    ];

                    continue;
                }

                $groupLimits[$group] = $limit;
            }

            $user->cards()->create($validated);
            $imported++;
        }

        return ['imported' => $imported, 'failed' => count($errors), 'errors' => $errors];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'json') {
            return $this->parseJson($file);
        }

        return $this->parseSpreadsheet($file, $extension);
    }

    private function parseJson(UploadedFile $file): array
    {
        $decoded = json_decode(file_get_contents($file->getRealPath()), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('The file is not valid JSON: '.json_last_error_msg());
        }

        if (! is_array($decoded) || array_is_list($decoded) === false) {
            throw new InvalidArgumentException('The JSON file must contain a top-level array of card objects.');
        }

        return $decoded;
    }

    private function parseSpreadsheet(UploadedFile $file, string $extension): array
    {
        $reader = match ($extension) {
            'csv', 'txt' => new Csv(),
            'xlsx' => new Xlsx(),
            'xls' => new Xls(),
            'ods' => new Ods(),
            default => throw new InvalidArgumentException("Unsupported file type: .{$extension}"),
        };

        $spreadsheet = $reader->load($file->getRealPath());
        $grid = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        if (count($grid) < 1) {
            return [];
        }

        $header = array_map(fn ($cell) => is_string($cell) ? trim($cell) : $cell, array_shift($grid));

        return array_map(function (array $line) use ($header) {
            $row = [];
            foreach ($header as $i => $key) {
                if ($key === null || $key === '') {
                    continue;
                }
                $row[$key] = $line[$i] ?? null;
            }

            return $row;
        }, $grid);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $row[$key] = null;
            }
        }

        if (isset($row['best_categories']) && is_string($row['best_categories'])) {
            $row['best_categories'] = array_values(array_filter(array_map(
                fn ($c) => strtolower(trim($c)),
                preg_split('/[,;|]/', $row['best_categories'])
            )));
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (isset($row[$field])) {
                $row[$field] = $this->normalizeBoolean($row[$field]);
            }
        }

        return $row;
    }

    private function normalizeBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'yes', 'y', 'true', '1' => true,
                'no', 'n', 'false', '0' => false,
                default => $value,
            };
        }

        return $value;
    }
}
