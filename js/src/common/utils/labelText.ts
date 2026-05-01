import app from 'flarum/common/app';
import extractText from 'flarum/common/utils/extractText';

/**
 * Translate a listing label, falling back to a clean uppercased version of the
 * raw key when no translation exists (so admin-defined labels like "olx" show
 * up as "OLX" rather than the raw `flarum-classifieds.lib.labels.olx` key).
 */
export default function labelText(label: string | null | undefined): string {
  if (!label) return '';

  const slug = String(label).toLowerCase().trim();
  const key = `flarum-classifieds.lib.labels.${slug}`;
  const translated = extractText(app.translator.trans(key));

  if (translated && translated !== key) {
    return translated;
  }

  return String(label).trim().toUpperCase();
}
