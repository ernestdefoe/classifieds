import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import humanTime from 'flarum/common/helpers/humanTime';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';

import formatPrice from '../../common/utils/formatPrice';
import labelText from '../../common/utils/labelText';

export interface ClassifiedsListingCardAttrs extends ComponentAttrs {
  discussion: Discussion;
}

export default class ClassifiedsListingCard extends Component<ClassifiedsListingCardAttrs> {
  view(): Mithril.Children {
    const d = this.attrs.discussion;
    const status = d.listingStatus() || 'active';
    const label = d.listingLabel();
    const images = d.listingImages?.() || [];
    const cover = images[0] || null;
    const price = formatPrice(d.listingPrice(), d.listingPriceMax(), d.listingCurrency());
    const date = (d as any).lastPostedAt?.() || (d as any).createdAt?.();
    const href = app.route.discussion(d as any);

    return (
      <section
        className={`olx-adcard olx-adcard__horizontal ClassifiedsListingCard ClassifiedsListingCard--${status}`}
        data-mode="horizontal"
      >
        <div className="olx-adcard__content" data-mode="horizontal">
          <div className="olx-adcard__topbody" data-mode="horizontal">
            <m.route.Link
              className="olx-adcard__link"
              href={href}
              title={d.title()}
            >
              <h2 className="olx-adcard__title">{d.title()}</h2>
            </m.route.Link>

            <div className="olx-adcard__badges">
              {label && (
                <div
                  className={`olx-core-badge olx-core-badge--medium olx-core-badge--pill ClassifiedsListingCard-labelBadge ClassifiedsListingCard-labelBadge--${String(label).toLowerCase()}`}
                >
                  {labelText(label)}
                </div>
              )}
              {status !== 'active' && (
                <div
                  className={`olx-core-badge olx-core-badge--medium olx-core-badge--pill ClassifiedsListingCard-statusBadge ClassifiedsListingCard-statusBadge--${status}`}
                >
                  <i
                    className={
                      'fas ' + (status === 'sold' ? 'fa-check-circle' : 'fa-flag-checkered')
                    }
                    aria-hidden="true"
                  />{' '}
                  {app.translator.trans(`flarum-classifieds.lib.statuses.${status}`)}
                </div>
              )}
            </div>
          </div>

          <div className="olx-adcard__mediumbody">
            {price && <h3 className="olx-adcard__price">{price}</h3>}
          </div>

          <div className="olx-adcard__bottombody">
            <div className="olx-adcard__location-date">
              {d.listingLocation() && (
                <p className="olx-adcard__location">
                  <i className="fas fa-map-marker-alt" aria-hidden="true" />{' '}
                  {d.listingLocation()}
                </p>
              )}
              {date && <p className="olx-adcard__date">{humanTime(date)}</p>}
            </div>
          </div>
        </div>

        <div className="ClassifiedsListingCard-mediaWrap">
          <div className="olx-adcard__media" data-mode="horizontal">
            {cover ? (
              <picture>
                <img src={cover} alt={d.title()} loading="lazy" decoding="async" />
              </picture>
            ) : (
              <div className="ClassifiedsListingCard-mediaPlaceholder">
                <i className="fas fa-camera" aria-hidden="true" />
              </div>
            )}
            {images.length > 1 && (
              <span className="ClassifiedsListingCard-counter">
                <i className="fas fa-images" aria-hidden="true" /> {images.length}
              </span>
            )}
          </div>
        </div>
      </section>
    );
  }
}
