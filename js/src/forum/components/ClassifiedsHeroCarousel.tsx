import Component, { ComponentAttrs } from 'flarum/common/Component';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

import type { ListingSeatMap } from '../../common/extendDiscussionModel';

export interface ClassifiedsHeroCarouselAttrs extends ComponentAttrs {
  images: string[];
  alt?: string;
  /**
   * The ground's seating chart, shown as the last slide.
   *
   * 🚨 A slide, not a link. On a ticket listing the chart IS the picture — most
   * have no photographs at all, so it becomes the main image rather than
   * something a buyer has to know to click.
   */
  seatMap?: ListingSeatMap | null;
  /** Opens the chart full size. */
  onExpandMap?: () => void;
}

export default class ClassifiedsHeroCarousel extends Component<ClassifiedsHeroCarouselAttrs> {
  index = 0;

  view(): Mithril.Children {
    const alt = this.attrs.alt || '';
    const map = this.attrs.seatMap?.image ? this.attrs.seatMap : null;

    // Photographs first, the chart last — a buyer looks at the goods, then at
    // where the seats are.
    const slides: Array<{ src: string; map?: ListingSeatMap }> = (this.attrs.images || []).map((src) => ({ src }));

    if (map) slides.push({ src: map.image!, map });

    if (!slides.length) return null;

    const images = slides;
    const i = Math.max(0, Math.min(this.index, slides.length - 1));
    const slide = slides[i];

    return (
      <div className="ClassifiedsHeroCarousel">
        <div className={classList('ClassifiedsHeroCarousel-frame', slide.map && 'ClassifiedsHeroCarousel-frame--map')}>
          {slide.map ? (
            /*
             * 🚨 The star is a child of a box with the CHART'S OWN aspect ratio,
             * not of the frame.
             *
             * The coordinates are fractions of the picture, and the frame is a
             * fixed-shape gallery slot, so `object-fit: contain` letterboxes the
             * chart inside it. Positioning the star as a percentage of the frame
             * then lands it on the wrong section — measured at 0.745 across when
             * the tracing said 0.683, about six points out, which on a stadium
             * bowl is a different part of the ground. Giving the wrapper the
             * chart's aspect ratio makes a percentage of it a percentage of the
             * picture again, at every size, with nothing to recompute on resize.
             */
            <div
              className="ClassifiedsHeroCarousel-mapWrap"
              style={{ aspectRatio: `${slide.map.width} / ${slide.map.height}` }}
            >
              <img src={slide.src} alt={slide.map.title} loading="lazy" decoding="async" />

              {slide.map.point && (
                <span
                  className="ClassifiedsHeroCarousel-star"
                  style={{ left: slide.map.point.x * 100 + '%', top: slide.map.point.y * 100 + '%' }}
                  aria-hidden="true"
                >
                  <i className="fas fa-star" />
                </span>
              )}
            </div>
          ) : (
            <picture>
              <img src={slide.src} alt={alt} loading="lazy" decoding="async" />
            </picture>
          )}

          {slide.map && <span className="ClassifiedsHeroCarousel-mapLabel">{slide.map.title}</span>}

          {/* Full size on click — the chart is legible inline, but a section
              number is small, and a buyer checking a seat wants to be sure. */}
          {slide.map && this.attrs.onExpandMap && (
            <button
              type="button"
              className="ClassifiedsHeroCarousel-expand"
              onclick={(e: MouseEvent) => {
                e.preventDefault();
                this.attrs.onExpandMap!();
              }}
              aria-label={slide.map.title}
            >
              <i className="fas fa-expand" aria-hidden="true" />
            </button>
          )}

          {images.length > 1 && (
            <>
              <button
                type="button"
                className="ClassifiedsHeroCarousel-nav ClassifiedsHeroCarousel-nav--prev"
                onclick={(e: MouseEvent) => {
                  e.preventDefault();
                  this.index = (i - 1 + images.length) % images.length;
                }}
                aria-label="Previous"
              >
                <i className="fas fa-chevron-left" aria-hidden="true" />
              </button>
              <button
                type="button"
                className="ClassifiedsHeroCarousel-nav ClassifiedsHeroCarousel-nav--next"
                onclick={(e: MouseEvent) => {
                  e.preventDefault();
                  this.index = (i + 1) % images.length;
                }}
                aria-label="Next"
              >
                <i className="fas fa-chevron-right" aria-hidden="true" />
              </button>

              <ul className="ClassifiedsHeroCarousel-bullets" aria-hidden="true">
                {images.map((_, idx) => (
                  <li
                    key={idx}
                    className={classList('ClassifiedsHeroCarousel-bullet', idx === i && 'is-active')}
                    onclick={() => {
                      this.index = idx;
                    }}
                  />
                ))}
              </ul>

              <span className="ClassifiedsHeroCarousel-counter">
                {i + 1} / {images.length}
              </span>
            </>
          )}
        </div>

        {images.length > 1 && (
          <div className="ClassifiedsHeroCarousel-thumbs">
            {images.map((url, idx) => (
              <button
                key={url}
                type="button"
                className={classList('ClassifiedsHeroCarousel-thumb', idx === i && 'is-active')}
                onclick={(e: MouseEvent) => {
                  e.preventDefault();
                  this.index = idx;
                }}
              >
                <img src={url} alt="" loading="lazy" />
              </button>
            ))}
          </div>
        )}
      </div>
    );
  }
}
