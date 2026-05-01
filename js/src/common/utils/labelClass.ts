export default function labelClass(label: string | null | undefined): string {
  if (!label) return '';

  return 'ClassifiedsLabel ClassifiedsLabel--' + String(label).toLowerCase();
}
