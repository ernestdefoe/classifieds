import app from 'flarum/common/app';

const CURRENCY_SYMBOLS: Record<string, string> = {
  USD: '$',
  EUR: '€',
  GBP: '£',
  JPY: '¥',
  BRL: 'R$',
  CAD: 'C$',
  AUD: 'A$',
  CHF: 'CHF',
  CNY: '¥',
  INR: '₹',
  MXN: 'MX$',
  ZAR: 'R',
};

function symbolFor(currency: string | null | undefined): string {
  if (!currency) return '';
  const code = currency.toUpperCase();
  if (CURRENCY_SYMBOLS[code]) return CURRENCY_SYMBOLS[code];
  return code + ' ';
}

/**
 * Resolve the currency code to use: prefer the listing's own currency, fall
 * back to the admin-configured default (`flarum-classifieds.default_currency`)
 * so the listing card never shows a hardcoded "R$" or "$" when the listing
 * itself has no currency set.
 */
function effectiveCurrency(currency: string | null | undefined): string | null {
  if (currency) return currency;
  const fallback = app.forum.attribute<string>('classifiedsDefaultCurrency');
  return fallback || null;
}

function formatNumber(value: number | string): string {
  const num = typeof value === 'number' ? value : parseFloat(value);

  if (Number.isNaN(num)) return String(value);

  return num.toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
}

export default function formatPrice(
  price: number | string | null | undefined,
  priceMax: number | string | null | undefined,
  currency: string | null | undefined
): string | null {
  if (price === null || price === undefined || price === '') return null;

  const code = effectiveCurrency(currency);
  const useSymbol = !!app.forum.attribute('classifiedsShowCurrencySymbol');
  const symbol = useSymbol ? symbolFor(code) : code ? code + ' ' : '';

  const min = symbol + formatNumber(price);

  if (
    priceMax !== null &&
    priceMax !== undefined &&
    priceMax !== '' &&
    parseFloat(String(priceMax)) > parseFloat(String(price))
  ) {
    return min + ' – ' + symbol + formatNumber(priceMax);
  }

  return min;
}
