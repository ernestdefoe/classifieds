import Component, { ComponentAttrs } from 'flarum/common/Component';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

export interface ClassifiedsHeroCarouselAttrs extends ComponentAttrs {
  images: string[];
  alt?: string;
}

export default class ClassifiedsHeroCarousel extends Component<ClassifiedsHeroCarouselAttrs> {
  index = 0;

  view(): Mithril.Children {
    const images = this.attrs.images || [];
    const alt = this.attrs.alt || '';

    if (!images.length) return null;

    const i = Math.max(0, Math.min(this.index, images.length - 1));

    return (
      <div className="ClassifiedsHeroCarousel">
        <div className="ClassifiedsHeroCarousel-frame">
          <picture>
            <img src={images[i]} alt={alt} loading="lazy" decoding="async" />
          </picture>

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
