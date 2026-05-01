import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import DiscussionHero from 'flarum/forum/components/DiscussionHero';
import Discussion from 'flarum/common/models/Discussion';
import Badge from 'flarum/common/components/Badge';
import humanTime from 'flarum/common/helpers/humanTime';
import labelText from '../common/utils/labelText';
import formatPrice from '../common/utils/formatPrice';
import ClassifiedsHeroCarousel from './components/ClassifiedsHeroCarousel';
import ClassifiedsBreadcrumb from './components/ClassifiedsBreadcrumb';
import ClassifiedsActions from './components/ClassifiedsActions';
import ClassifiedsSellerCard from './components/ClassifiedsSellerCard';

/**
 * For classifieds discussions we replace the entire DiscussionHero with a 2-column,
 * OLX-inspired layout: photo gallery on the left, all listing meta on the right.
 * For everything else, the standard hero (with flarum-tags color, etc.) renders
 * unchanged via the original implementation.
 */
export default function addListingHeroBadge(): void {
  extend(Discussion.prototype, 'badges', function (this: Discussion, badges: any) {
    if (!this.isClassifieds()) return;

    const status = this.listingStatus() || 'active';

    if (status !== 'active') {
      badges.add(
        'classifiedsStatus',
        <Badge
          type={`classifieds-${status}`}
          icon={status === 'sold' ? 'fas fa-check-circle' : 'fas fa-flag-checkered'}
          label={app.translator.trans(`flarum-classifieds.lib.statuses.${status}`)}
        />,
        20
      );
    }
  });

  override(DiscussionHero.prototype, 'view', function (this: any, original: () => any) {
    const discussion: Discussion | undefined = this.attrs?.discussion;

    if (!discussion?.isClassifieds?.()) return original();

    const images = discussion.listingImages?.() || [];
    const label = discussion.listingLabel();
    const status = discussion.listingStatus() || 'active';
    const price = formatPrice(
      discussion.listingPrice(),
      discussion.listingPriceMax(),
      discussion.listingCurrency()
    );
    const location = discussion.listingLocation();
    const date = (discussion as any).lastPostedAt?.() || (discussion as any).createdAt?.();

    const tags = (discussion as any).tags?.() || [];

    // Pick the first classifieds tag's color (or any tag's color as fallback)
    // to tint the hero subtly. Falls back to the theme primary if no color.
    const classifiedsTag = tags.find(
      (t: any) => t && typeof t.isClassifieds === 'function' && t.isClassifieds()
    );
    const tintTag = classifiedsTag || tags.find((t: any) => t && typeof t.color === 'function' && t.color());
    const tintColor: string = (tintTag && tintTag.color?.()) || '';

    const tagPills = tags
      .filter((t: any) => t && typeof t.color === 'function')
      .map((tag: any) => (
        <a
          className="TagLabel colored text-contrast--light"
          style={{ '--tag-bg': tag.color() } as any}
          href={app.route('tag', { tags: tag.slug() })}
          config={(m as any).route.link}
          title={tag.description?.() || ''}
        >
          <span className="TagLabel-text">
            {tag.icon?.() && <i className={`TagLabel-icon icon text-colored ${tag.icon()}`} />}
            <span className="TagLabel-name">{tag.name?.()}</span>
          </span>
        </a>
      ));

    return (
      <header
        className="Hero DiscussionHero DiscussionHero--classifieds"
        style={tintColor ? ({ '--classifieds-tint': tintColor } as any) : undefined}
      >
        <div className="container">
          <ClassifiedsBreadcrumb discussion={discussion} />

          <div className="ClassifiedsHero">
            <div className="ClassifiedsHero-gallery">
              {images.length ? (
                <ClassifiedsHeroCarousel images={images} alt={discussion.title()} />
              ) : (
                <div className="ClassifiedsHero-noImage">
                  <i className="fas fa-camera" aria-hidden="true" />
                </div>
              )}
            </div>

            <div className="ClassifiedsHero-info">
              {date && (
                <div className="ClassifiedsHero-postedAt">
                  <i className="far fa-clock" aria-hidden="true" /> {humanTime(date)}
                </div>
              )}

              <h1 className="ClassifiedsHero-title">{discussion.title()}</h1>

              {price && <div className="ClassifiedsHero-price">{price}</div>}

              {(label || status !== 'active' || tagPills.length) && (
                <div className="ClassifiedsHero-chips">
                  {tagPills.length > 0 && <span className="TagsLabel">{tagPills}</span>}
                  {label && (
                    <span
                      className={`TagLabel colored text-contrast--light ClassifiedsTagLabel ClassifiedsTagLabel--${String(label).toLowerCase()}`}
                      title={labelText(label)}
                    >
                      <span className="TagLabel-text">
                        <i className="TagLabel-icon icon text-colored fas fa-tag" />
                        <span className="TagLabel-name">{labelText(label)}</span>
                      </span>
                    </span>
                  )}
                  {status !== 'active' && (
                    <span className={`ClassifiedsHero-statusBadge ClassifiedsHero-statusBadge--${status}`}>
                      <i
                        className={
                          'fas ' + (status === 'sold' ? 'fa-check-circle' : 'fa-flag-checkered')
                        }
                        aria-hidden="true"
                      />{' '}
                      {app.translator.trans(`flarum-classifieds.lib.statuses.${status}`)}
                    </span>
                  )}
                </div>
              )}

              {location && (
                <div className="ClassifiedsHero-location">
                  <i className="fas fa-map-marker-alt" aria-hidden="true" />
                  <span>{location}</span>
                </div>
              )}

              <ClassifiedsActions discussion={discussion} />

              <div className="ClassifiedsHero-divider" aria-hidden="true" />

              <ClassifiedsSellerCard discussion={discussion} />
            </div>
          </div>
        </div>
      </header>
    );
  });
}
