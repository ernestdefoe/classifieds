import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';

export interface ClassifiedsSellerCardAttrs extends ComponentAttrs {
  discussion: Discussion;
}

export default class ClassifiedsSellerCard extends Component<ClassifiedsSellerCardAttrs> {
  view(): Mithril.Children {
    const discussion = this.attrs.discussion;
    const user: any = discussion.user?.();

    if (!user) return null;

    const profileHref = (app as any).route?.user?.(user) || `/u/${user.username?.()}`;
    const joined = user.joinTime?.();
    // Only listings in classifieds tags — not the user's total forum
    // discussion count (which would include unrelated topics).
    const listingsCount = typeof user.classifiedsListingsCount === 'function'
      ? user.classifiedsListingsCount()
      : null;
    const lastSeen = user.lastSeenAt?.();

    return (
      <div className="ClassifiedsSellerCard">
        <div className="ClassifiedsSellerCard-header">
          <span className="ClassifiedsSellerCard-label">
            {app.translator.trans('flarum-classifieds.forum.seller.title')}
          </span>
        </div>

        <a className="ClassifiedsSellerCard-body" href={profileHref} config={(m as any).route.link}>
          <Avatar user={user} className="ClassifiedsSellerCard-avatar" />
          <div className="ClassifiedsSellerCard-info">
            <span className="ClassifiedsSellerCard-name">{username(user)}</span>
            {joined && (
              <span className="ClassifiedsSellerCard-meta">
                <i className="fas fa-clock" aria-hidden="true" />{' '}
                {app.translator.trans('flarum-classifieds.forum.seller.member_since', {
                  time: humanTime(joined),
                })}
              </span>
            )}
            {typeof listingsCount === 'number' && (
              <span className="ClassifiedsSellerCard-meta">
                <i className="fas fa-tag" aria-hidden="true" />{' '}
                {app.translator.trans('flarum-classifieds.forum.seller.listings', {
                  count: listingsCount,
                })}
              </span>
            )}
            {lastSeen && (
              <span className="ClassifiedsSellerCard-meta ClassifiedsSellerCard-meta--online">
                <span className="ClassifiedsSellerCard-dot" />{' '}
                {app.translator.trans('flarum-classifieds.forum.seller.last_seen', {
                  time: humanTime(lastSeen),
                })}
              </span>
            )}
          </div>
        </a>
      </div>
    );
  }
}
