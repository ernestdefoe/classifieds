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
  section?: string;
  row?: string;
  seats?: string;
  seatmapId?: number | '' | null;
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

        {/*
          🚨 A separate row, and only for ticket listings.
          Section, row and seat mean nothing on a listing for a sofa, and three
          empty boxes on every ad is three more things to read past. The row
          shows when the seller has said this is a ticket — see isTicketListing.
        */}
        {this.showSeatFields() && (
          <div className="ClassifiedsComposer-row ClassifiedsComposer-seatRow">
            <input
              className="FormControl ClassifiedsComposer-sectionInput"
              type="text"
              maxlength={32}
              value={listing.section || ''}
              oninput={(e: InputEvent) => (listing.section = (e.target as HTMLInputElement).value)}
              placeholder={app.translator.trans('flarum-classifieds.forum.composer.section_placeholder', {}, true) as string}
              aria-label={app.translator.trans('flarum-classifieds.forum.composer.section_label', {}, true) as string}
            />

            <input
              className="FormControl ClassifiedsComposer-rowInput"
              type="text"
              maxlength={16}
              value={listing.row || ''}
              oninput={(e: InputEvent) => (listing.row = (e.target as HTMLInputElement).value)}
              placeholder={app.translator.trans('flarum-classifieds.forum.composer.row_placeholder', {}, true) as string}
              aria-label={app.translator.trans('flarum-classifieds.forum.composer.row_label', {}, true) as string}
            />

            <input
              className="FormControl ClassifiedsComposer-seatsInput"
              type="text"
              maxlength={64}
              value={listing.seats || ''}
              oninput={(e: InputEvent) => (listing.seats = (e.target as HTMLInputElement).value)}
              placeholder={app.translator.trans('flarum-classifieds.forum.composer.seats_placeholder', {}, true) as string}
              aria-label={app.translator.trans('flarum-classifieds.forum.composer.seats_label', {}, true) as string}
            />
          </div>
        )}

        {/*
          🚨 The ground picker shows only when charts have been traced.

          An empty select labelled "Stadium" is a control that is there, worded
          and styled, and can do nothing — and the seller has no way to know
          that the reason is an admin who has not traced a chart yet. With no
          charts, the seat row stands on its own exactly as it did before.
        */}
        {this.showSeatFields() && this.seatMaps().length > 0 && (
          <div className="ClassifiedsComposer-row ClassifiedsComposer-mapRow">
            <select
              className="FormControl ClassifiedsComposer-mapSelect"
              value={listing.seatmapId ?? ''}
              onchange={(e: Event) => {
                const raw = (e.target as HTMLSelectElement).value;
                // '' means "no ground", never chart 0 — the server reads it the
                // same way, so a cleared select clears the binding.
                listing.seatmapId = raw === '' ? '' : Number(raw);
              }}
              aria-label={app.translator.trans('flarum-classifieds.forum.composer.seatmap_label', {}, true) as string}
            >
              <option value="">{app.translator.trans('flarum-classifieds.forum.composer.seatmap_none')}</option>
              {this.seatMaps().map((map: any) => (
                <option value={map.id} key={map.id}>
                  {map.title}
                </option>
              ))}
            </select>
          </div>
        )}
      </div>
    );
  }

  /**
   * Whether this listing looks like tickets.
   *
   * 🚨 Shown whenever ANY seat field already has a value, not only when the
   * tag matches. An admin can rename or re-tag a listing later, and a field
   * that disappears while still holding data is a field somebody cannot clear.
   */
  /** The stadium charts an admin has actually traced. */
  seatMaps(): any[] {
    return app.forum.attribute<any[]>('classifiedsSeatMaps') || [];
  }

  showSeatFields(): boolean {
    const listing = this.attrs.composer.fields.listing || {};

    if (listing.section || listing.row || listing.seats) return true;

    const tags = this.attrs.composer.fields.tags || [];
    const needle = (app.forum.attribute<string>('classifiedsTicketTagSlugs') || 'tickets,classifieds')
      .split(',')
      .map((t) => t.trim().toLowerCase())
      .filter(Boolean);

    return tags.some((t: any) => {
      const slug = typeof t?.slug === 'function' ? t.slug() : t?.slug;
      return slug && needle.includes(String(slug).toLowerCase());
    });
  }
}
