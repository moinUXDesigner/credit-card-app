// Logo files sourced from Wikimedia Commons (https://commons.wikimedia.org), linked directly to
// their stable upload.wikimedia.org asset path (resolved from each file's Special:FilePath redirect)
// rather than hotlinking the redirect itself, which proved unreliable under repeated requests.
// These are trademarked bank logos used here solely to visually identify which bank a card belongs
// to — not an endorsement by, or affiliation with, any bank.
const UPLOAD = 'https://upload.wikimedia.org/wikipedia/commons/'

const BANK_LOGOS = {
  'hdfc bank': `${UPLOAD}2/28/HDFC_Bank_Logo.svg`,
  hdfc: `${UPLOAD}2/28/HDFC_Bank_Logo.svg`,
  'state bank of india': `${UPLOAD}3/33/State_Bank_of_India.svg`,
  sbi: `${UPLOAD}3/33/State_Bank_of_India.svg`,
  'icici bank': `${UPLOAD}1/12/ICICI_Bank_Logo.svg`,
  icici: `${UPLOAD}1/12/ICICI_Bank_Logo.svg`,
  'axis bank': `${UPLOAD}1/1a/Axis_Bank_logo.svg`,
  axis: `${UPLOAD}1/1a/Axis_Bank_logo.svg`,
  'rbl bank': `${UPLOAD}7/70/RBL_Bank_SVG_Logo.svg`,
  rbl: `${UPLOAD}7/70/RBL_Bank_SVG_Logo.svg`,
  'punjab national bank': `${UPLOAD}b/b2/Punjab_National_Bank_new_logo.svg`,
  pnb: `${UPLOAD}b/b2/Punjab_National_Bank_new_logo.svg`,
  'bank of baroda': `${UPLOAD}d/df/Bank_of_Baroda_Logo_since_Dec_19.png`,
  bob: `${UPLOAD}d/df/Bank_of_Baroda_Logo_since_Dec_19.png`,
  'indusind bank': `${UPLOAD}4/40/IndusInd_Bank_SVG_Logo.svg`,
  indusind: `${UPLOAD}4/40/IndusInd_Bank_SVG_Logo.svg`,
  'yes bank': `${UPLOAD}4/4f/Yes_Bank_SVG_Logo.svg`,
  yes: `${UPLOAD}4/4f/Yes_Bank_SVG_Logo.svg`,
  'idfc first bank': `${UPLOAD}3/3f/Logo_of_IDFC_First_Bank.svg`,
  'idfc first': `${UPLOAD}3/3f/Logo_of_IDFC_First_Bank.svg`,
  idfc: `${UPLOAD}3/3f/Logo_of_IDFC_First_Bank.svg`,
  'standard chartered': `${UPLOAD}0/0c/Standard_Chartered_(2021).svg`,
  'standard chartered bank': `${UPLOAD}0/0c/Standard_Chartered_(2021).svg`,
  'american express': `${UPLOAD}f/fa/American_Express_logo_(2018).svg`,
  amex: `${UPLOAD}f/fa/American_Express_logo_(2018).svg`,
  hsbc: `${UPLOAD}a/aa/HSBC_logo_(2018).svg`,
  citibank: `${UPLOAD}1/1b/Citi.svg`,
  citi: `${UPLOAD}1/1b/Citi.svg`,
}

export function getBankLogo(bankName) {
  if (!bankName) return null
  return BANK_LOGOS[bankName.trim().toLowerCase()] ?? null
}
