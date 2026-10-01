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
  section!: Stream<string>;
  row!: Stream<string>;
  seats!: Stream<string>;
  seatmapId!: Stream<string>;

  seatMaps(): any[] {
    return app.forum.attribute<any[]>('classifiedsSeatMaps') || [];
  }

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
    this.section = Stream(discussion.listingSection() || '');
    this.row = Stream(discussion.listingRow() || '');
    this.seats = Stream(discussion.listingSeats() || '');
    this.seatmapId = Stream(String(discussion.listingSeatmapId?.() || ''));
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

          {/* Seat details. Shown for every listing here, unlike the composer —
              an editor is already looking at one specific ad and may be adding
              seats to something that was not tagged as tickets when posted. */}
          <div className="Form-group ClassifiedsEdit-seats">
            <label>{app.translator.trans('flarum-classifieds.forum.composer.section_label')}</label>
            <input className="FormControl" type="text" maxlength="32" bidi={this.section} />

            <label>{app.translator.trans('flarum-classifieds.forum.composer.row_label')}</label>
            <input className="FormControl" type="text" maxlength="16" bidi={this.row} />

            <label>{app.translator.trans('flarum-classifieds.forum.composer.seats_label')}</label>
            <input className="FormControl" type="text" maxlength="64" bidi={this.seats} />

            {/* Only when charts exist — see the note on the composer's picker. */}
            {this.seatMaps().length > 0 && [
              <label>{app.translator.trans('flarum-classifieds.forum.composer.seatmap_label')}</label>,
              <select className="FormControl" bidi={this.seatmapId}>
                <option value="">{app.translator.trans('flarum-classifieds.forum.composer.seatmap_none')}</option>
                {this.seatMaps().map((map: any) => (
                  <option value={String(map.id)} key={map.id}>
                    {map.title}
                  </option>
                ))}
              </select>,
            ]}
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
        listingSection: this.section() || null,
        listingRow: this.row() || null,
        listingSeats: this.seats() || null,
        listingSeatmapId: this.seatmapId() ? Number(this.seatmapId()) : null,
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
