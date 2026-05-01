import app from 'flarum/common/app';
import FormModal, { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';

import labelText from '../../common/utils/labelText';

export interface EditListingModalAttrs extends IFormModalAttrs {
  discussion: Discussion;
}

export default class EditListingModal extends FormModal<EditListingModalAttrs> {
  label!: Stream<string>;
  price!: Stream<string>;
  priceMax!: Stream<string>;
  currency!: Stream<string>;
  location!: Stream<string>;

  oninit(vnode: Mithril.Vnode<EditListingModalAttrs, this>) {
    super.oninit(vnode);

    const discussion = this.attrs.discussion;

    this.label = Stream(discussion.listingLabel() || '');
    this.price = Stream(discussion.listingPrice() != null ? String(discussion.listingPrice()) : '');
    this.priceMax = Stream(discussion.listingPriceMax() != null ? String(discussion.listingPriceMax()) : '');
    this.currency = Stream(
      discussion.listingCurrency() ||
        (app.forum.attribute<string>('classifiedsDefaultCurrency') as string | undefined) ||
        'USD'
    );
    this.location = Stream(discussion.listingLocation() || '');
  }

  className(): string {
    return 'EditListingModal Modal--small';
  }

  title(): Mithril.Children {
    return app.translator.trans('flarum-classifieds.forum.edit_listing.title');
  }

  content(): Mithril.Children {
    const labels = (
      (app.forum.attribute<string>('classifiedsAllowedLabels') as string | undefined) || 'iso,wtb,wts,trade'
    )
      .split(',')
      .map((l: string) => l.trim())
      .filter(Boolean);

    const labelOptions: Record<string, string> = {
      '': app.translator.trans('flarum-classifieds.forum.composer.label_placeholder', {}, true) as string,
    };
    labels.forEach((l: string) => {
      labelOptions[l] = labelText(l);
    });

    const allowRange = !!app.forum.attribute('classifiedsAllowPriceRange');

    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{app.translator.trans('flarum-classifieds.forum.composer.label_label')}</label>
            <Select options={labelOptions} value={this.label()} onchange={this.label} />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('flarum-classifieds.forum.composer.price_label')}</label>
            <input className="FormControl" type="number" step="0.01" min="0" bidi={this.price} />
          </div>

          {allowRange && (
            <div className="Form-group">
              <label>{app.translator.trans('flarum-classifieds.forum.composer.price_max_label')}</label>
              <input className="FormControl" type="number" step="0.01" min="0" bidi={this.priceMax} />
            </div>
          )}

          <div className="Form-group">
            <label>{app.translator.trans('flarum-classifieds.forum.composer.currency_label')}</label>
            <input className="FormControl" type="text" maxlength="8" bidi={this.currency} />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('flarum-classifieds.forum.composer.location_label')}</label>
            <input className="FormControl" type="text" maxlength="255" bidi={this.location} />
          </div>

          <div className="Form-group">
            <Button className="Button Button--primary" type="submit" loading={this.loading}>
              {app.translator.trans('flarum-classifieds.forum.edit_listing.save_button')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    this.loading = true;

    const discussion = this.attrs.discussion;

    discussion
      .save({
        listingLabel: this.label() || null,
        listingPrice: this.price() === '' ? null : this.price(),
        listingPriceMax: this.priceMax() === '' ? null : this.priceMax(),
        listingCurrency: this.currency() || null,
        listingLocation: this.location() || null,
      } as any)
      .then(
        () => {
          this.loading = false;
          this.hide();
          m.redraw();
        },
        (err: any) => {
          this.loading = false;
          this.loaded();
          throw err;
        }
      );
  }
}
