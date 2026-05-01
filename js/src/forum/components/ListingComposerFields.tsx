import app from 'flarum/common/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

import labelText from '../../common/utils/labelText';
import ListingImageUploader, { PendingImage } from './ListingImageUploader';

export interface ListingFields {
  label?: string;
  status?: string;
  price?: string | number | '';
  priceMax?: string | number | '';
  currency?: string;
  location?: string;
  pendingImages?: PendingImage[];
  uploadedImages?: string[];
}

interface IComposer {
  fields: { listing?: ListingFields; tags?: any[] } & Record<string, any>;
}

export interface ListingComposerFieldsAttrs extends ComponentAttrs {
  composer: IComposer;
}

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
  return CURRENCY_SYMBOLS[currency.toUpperCase()] || currency.toUpperCase();
}

export default class ListingComposerFields extends Component<ListingComposerFieldsAttrs> {
  view(): Mithril.Children {
    const composer = this.attrs.composer;
    const listing = (composer.fields.listing = composer.fields.listing || {});

    const labels = (
      (app.forum.attribute<string>('classifiedsAllowedLabels') as string | undefined) || 'iso,wtb,wts,trade'
    )
      .split(',')
      .map((l: string) => l.trim())
      .filter(Boolean);

    const allowRange = !!app.forum.attribute('classifiedsAllowPriceRange');
    const requireLabel = !!app.forum.attribute('classifiedsRequireLabel');
    const requirePrice = !!app.forum.attribute('classifiedsRequirePrice');
    const requireLocation = !!app.forum.attribute('classifiedsRequireLocation');

    // If the listing has no currency yet, fall back to the admin-configured
    // default — never let "R$" or any symbol stay hardcoded.
    const effectiveCurrency =
      listing.currency || (app.forum.attribute<string>('classifiedsDefaultCurrency') as string | undefined) || '';
    const symbol = symbolFor(effectiveCurrency);

    listing.pendingImages = listing.pendingImages || [];
    listing.uploadedImages = listing.uploadedImages || [];

    return (
      <div className="ClassifiedsComposer">
        <ListingImageUploader
          pending={listing.pendingImages}
          uploaded={listing.uploadedImages}
          onChangePending={(next) => {
            listing.pendingImages = next;
          }}
          onRemoveUploaded={(url) => {
            listing.uploadedImages = (listing.uploadedImages || []).filter((u) => u !== url);
          }}
        />

        <div className="ClassifiedsComposer-pills" role="radiogroup" aria-label={
          app.translator.trans('flarum-classifieds.forum.composer.label_label', {}, true) as string
        }>
          {labels.map((l) => (
            <button
              key={l}
              type="button"
              role="radio"
              aria-checked={listing.label === l}
              title={labelText(l)}
              className={classList(
                'Button Button--ua-reset ClassifiedsComposer-pill',
                listing.label === l && 'is-active',
                `ClassifiedsComposer-pill--${l.toLowerCase()}`
              )}
              onclick={(e: MouseEvent) => {
                e.preventDefault();
                listing.label = listing.label === l ? '' : l;
              }}
            >
              {labelText(l)}
            </button>
          ))}
          {requireLabel && !listing.label && (
            <span className="ClassifiedsComposer-required" aria-hidden="true">*</span>
          )}
        </div>

        <div className="ClassifiedsComposer-row">
          <div className="ClassifiedsComposer-priceInput" data-symbol={symbol}>
            <input
              className="FormControl"
              type="number"
              min="0"
              step="0.01"
              value={listing.price ?? ''}
              oninput={(e: InputEvent) => (listing.price = (e.target as HTMLInputElement).value)}
              placeholder={
                (app.translator.trans(
                  'flarum-classifieds.forum.composer.price_label',
                  {},
                  true
                ) as string) + (requirePrice ? ' *' : '')
              }
              aria-label={app.translator.trans('flarum-classifieds.forum.composer.price_label', {}, true) as string}
            />
          </div>

          {allowRange && (
            <div className="ClassifiedsComposer-priceInput" data-symbol={symbol}>
              <input
                className="FormControl"
                type="number"
                min="0"
                step="0.01"
                value={listing.priceMax ?? ''}
                oninput={(e: InputEvent) => (listing.priceMax = (e.target as HTMLInputElement).value)}
                placeholder={
                  app.translator.trans(
                    'flarum-classifieds.forum.composer.price_max_label',
                    {},
                    true
                  ) as string
                }
                aria-label={
                  app.translator.trans('flarum-classifieds.forum.composer.price_max_label', {}, true) as string
                }
              />
            </div>
          )}

          <input
            className="FormControl ClassifiedsComposer-currencyInput"
            type="text"
            maxlength={8}
            value={listing.currency || ''}
            oninput={(e: InputEvent) =>
              (listing.currency = (e.target as HTMLInputElement).value.toUpperCase())
            }
            placeholder={
              (app.forum.attribute<string>('classifiedsDefaultCurrency') as string | undefined) ||
              (app.translator.trans('flarum-classifieds.forum.composer.currency_label', {}, true) as string)
            }
            aria-label={app.translator.trans('flarum-classifieds.forum.composer.currency_label', {}, true) as string}
          />

          <input
            className="FormControl ClassifiedsComposer-locationInput"
            type="text"
            maxlength={255}
            value={listing.location || ''}
            oninput={(e: InputEvent) => (listing.location = (e.target as HTMLInputElement).value)}
            placeholder={
              (app.translator.trans(
                'flarum-classifieds.forum.composer.location_placeholder',
                {},
                true
              ) as string) + (requireLocation ? ' *' : '')
            }
            aria-label={app.translator.trans('flarum-classifieds.forum.composer.location_label', {}, true) as string}
          />
        </div>
      </div>
    );
  }
}
