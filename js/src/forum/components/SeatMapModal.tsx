import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Discussion from 'flarum/common/models/Discussion';

import type { ListingSeatMap } from '../../common/extendDiscussionModel';

export interface SeatMapModalAttrs extends IInternalModalAttrs {
  discussion: Discussion;
}

/**
 * Where these seats are, on the ground's own seating chart.
 *
 * 🚨 The star is drawn only when the server resolved a point.
 *
 * Section numbering is not standardised between grounds, so there is no rule
 * that turns "114" into a position — only a tracing somebody did for this one
 * chart. When that section was never traced the chart still shows, because it
 * is useful on its own, but the listing's own words stand in place of a star.
 * A star in the wrong half of a ground is worse than none: a buyer believes a
 * picture over a sentence, and buys the wrong ticket.
 */
export default class SeatMapModal extends Modal<SeatMapModalAttrs> {
  className() {
    return 'Modal--large ClassifiedsSeatMap-modal';
  }

  title() {
    return this.map()?.title || app.translator.trans('flarum-classifieds.forum.seatmap.title');
  }

  map(): ListingSeatMap | null {
    return (this.attrs.discussion as any).listingSeatMap?.() || null;
  }

  content() {
    const map = this.map();

    if (!map?.image) {
      return <div className="Modal-body">{app.translator.trans('flarum-classifieds.forum.seatmap.unavailable')}</div>;
    }

    const point = map.point;
    const seats = (this.attrs.discussion as any).listingSeatDisplay?.();
    const section = (this.attrs.discussion as any).listingSection?.();

    return (
      <div className="Modal-body ClassifiedsSeatMap">
        <div className="ClassifiedsSeatMap-stage">
          <img className="ClassifiedsSeatMap-image" src={map.image} alt={map.title} />

          {point && (
            <span
              className="ClassifiedsSeatMap-star"
              style={{ left: point.x * 100 + '%', top: point.y * 100 + '%' }}
              aria-label={app.translator.trans('flarum-classifieds.forum.seatmap.marker', { section }, true) as string}
            >
              <i className="fas fa-star" aria-hidden="true" />
            </span>
          )}
        </div>

        <p className="ClassifiedsSeatMap-caption">
          {seats}
          {/*
            🚨 Said out loud when the section is not on the chart, rather than
            showing an unmarked picture and leaving the reader to wonder which
            of the two of them is broken.
          */}
          {!point && section && (
            <span className="ClassifiedsSeatMap-untraced">
              {app.translator.trans('flarum-classifieds.forum.seatmap.not_marked', { section })}
            </span>
          )}
        </p>
      </div>
    );
  }
}
