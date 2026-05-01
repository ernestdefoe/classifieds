import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';

import ListingComposerFields, { ListingFields } from './components/ListingComposerFields';
import uploadListingImage from '../common/utils/uploadListingImage';

interface TagWithClassifieds {
  isClassifieds?: () => boolean;
}

/**
 * The classifieds composer extension is gated SOLELY on whether at least one
 * of the discussion's selected tags is flagged as a classifieds tag in the
 * admin (server-side `tags.is_classifieds = 1`, exposed to the front-end via
 * `Tag.prototype.isClassifieds`). No matter how the composer is opened —
 * IndexSidebar, custom theme button, deep link — none of the classifieds
 * fields, height bump, validation or image-upload flow activates unless this
 * gate returns true.
 */
function isClassifiedsContext(this_: any): boolean {
  const tags: TagWithClassifieds[] | undefined = this_?.composer?.fields?.tags;
  if (!tags || !tags.length) return false;

  return tags.some(
    (tag) => tag && typeof tag.isClassifieds === 'function' && tag.isClassifieds() === true
  );
}

function ensureListingState(composer: any): ListingFields {
  if (!composer.fields.listing) {
    // Currency stays empty by default — the admin's `default_currency`
    // setting is shown as a placeholder hint and used for display fallback,
    // so changing the admin default flows naturally to existing listings
    // without a per-listing override locked in at create time.
    composer.fields.listing = {
      label: '',
      price: '',
      priceMax: '',
      currency: '',
      location: '',
    };
  }
  return composer.fields.listing;
}

export default function addListingComposer(): void {
  // No `composer.height` mutation — that field is a singleton on
  // ComposerState that persists across loads and was leaking into
  // non-classifieds composers (and reply/edit composers too). Keep the
  // user's preferred height intact; if more room is needed for the listing
  // fields the user can drag the resize handle exactly like with any other
  // composer body.

  // Inject the listing fields into the composer header. Initialization of
  // `composer.fields.listing` happens lazily here so non-classifieds composers
  // never carry classifieds state.
  extend('flarum/forum/components/DiscussionComposer', 'headerItems', function (this: any, items: any) {
    if (!isClassifiedsContext(this)) return;

    ensureListingState(this.composer);

    items.add(
      'classifieds',
      <ListingComposerFields composer={this.composer} />,
      -10
    );
  });

  // Add `listingLabel/Price/PriceMax/Currency/Location` to the JSON:API attrs
  // sent on save — only for classifieds discussions. Non-classifieds saves
  // never see any classifieds field.
  override('flarum/forum/components/DiscussionComposer', 'data', function (
    this: any,
    original: () => Record<string, any>
  ) {
    const data = original();

    if (!isClassifiedsContext(this)) return data;

    const listing: ListingFields = this.composer.fields.listing || {};

    data.listingLabel = listing.label || null;
    data.listingPrice = listing.price === '' || listing.price == null ? null : listing.price;
    data.listingPriceMax = listing.priceMax === '' || listing.priceMax == null ? null : listing.priceMax;
    data.listingCurrency = listing.currency || null;
    data.listingLocation = listing.location || null;

    return data;
  });

  override('flarum/forum/components/DiscussionComposer', 'onsubmit', function (
    this: any,
    original: () => unknown
  ) {
    if (!isClassifiedsContext(this)) return original();

    const listing: ListingFields = this.composer.fields.listing || {};
    const requireLabel = !!app.forum.attribute('classifiedsRequireLabel');
    const requirePrice = !!app.forum.attribute('classifiedsRequirePrice');
    const requireLocation = !!app.forum.attribute('classifiedsRequireLocation');

    if (requireLabel && !listing.label) {
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('flarum-classifieds.forum.classifieds_composer.label_required')
      );
      return;
    }

    if (requirePrice && (listing.price === '' || listing.price == null)) {
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('flarum-classifieds.forum.classifieds_composer.price_required')
      );
      return;
    }

    if (requireLocation && !listing.location) {
      app.alerts.show(
        { type: 'error' },
        app.translator.trans('flarum-classifieds.forum.classifieds_composer.location_required')
      );
      return;
    }

    // If there are pending images, mirror DiscussionComposer's save flow but
    // upload images between save and redirect, so the discussion page already
    // has its screenshots when navigated to.
    const pending = listing.pendingImages || [];
    if (pending.length === 0) return original();

    this.loading = true;
    m.redraw();

    const data = this.data();

    app.store
      .createRecord('discussions')
      .save(data)
      .then(async (discussion: any) => {
        for (const img of pending) {
          try {
            await uploadListingImage(discussion.id(), img.file);
          } catch (e) {
            // eslint-disable-next-line no-console
            console.warn('[classifieds] image upload failed', e);
          }
        }

        this.composer.hide();
        app.discussions?.refresh?.();
        m.route.set(app.route.discussion(discussion));
      }, this.loaded.bind(this));
  });
}
