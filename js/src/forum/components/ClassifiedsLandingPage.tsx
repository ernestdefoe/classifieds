import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import IndexPage from 'flarum/forum/components/IndexPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

interface TagLike {
  id: () => string | number;
  name: () => string;
  slug: () => string;
  description: () => string | null;
  color: () => string | null;
  icon: () => string | null;
  isClassifieds?: () => boolean;
  discussionCount?: () => number;
}

export default class ClassifiedsLandingPage extends Page {
  loading = true;
  classifiedsTags: TagLike[] = [];

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    this.bodyClass = 'App--index ClassifiedsLandingPage-body';
    app.setTitle(
      extractText(app.translator.trans('flarum-classifieds.forum.landing.title')) as string
    );

    this.loadTags();
  }

  loadTags(): void {
    this.loading = true;

    app.store
      .find('tags', { include: 'parent' })
      .then((tags: any[]) => {
        this.classifiedsTags = (tags || [])
          .filter((t: any) => t && typeof t.isClassifieds === 'function' && t.isClassifieds());
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view(): Mithril.Children {
    const user = app.session.user;
    const greeting = user
      ? app.translator.trans('flarum-classifieds.forum.landing.greeting', {
          username: (user as any).displayName?.() || (user as any).username?.(),
        })
      : app.translator.trans('flarum-classifieds.forum.landing.greeting_guest');

    return (
      <div className="ClassifiedsLanding">
        <header className="ClassifiedsLanding-header">
          <span className="ClassifiedsLanding-greeting">{greeting}</span>
          <span className="ClassifiedsLanding-prompt">
            {app.translator.trans('flarum-classifieds.forum.landing.prompt')}
          </span>
        </header>

        {this.loading ? (
          <LoadingIndicator />
        ) : this.classifiedsTags.length === 0 ? (
          <p className="ClassifiedsLanding-empty">
            {app.translator.trans('flarum-classifieds.forum.landing.empty')}
          </p>
        ) : (
          <div className="ClassifiedsLanding-grid">
            {this.classifiedsTags.map((tag, idx) => this.renderCategoryCard(tag, idx))}
          </div>
        )}
      </div>
    );
  }

  renderCategoryCard(tag: TagLike, idx: number): Mithril.Children {
    const color = tag.color() || 'var(--primary-color)';
    const icon = tag.icon() || 'fas fa-tag';
    const slug = tag.slug();
    const count = typeof tag.discussionCount === 'function' ? tag.discussionCount() : 0;

    return (
      <a
        key={String(tag.id())}
        className={classList(
          'ClassifiedsLanding-card',
          `ClassifiedsLanding-card--${idx}`
        )}
        style={{ '--tag-color': color } as any}
        href={app.route('tag', { tags: slug })}
        config={m.route.link as any}
        aria-label={extractText(app.translator.trans('flarum-classifieds.forum.landing.select_category', { name: tag.name() })) as string}
      >
        <div className="ClassifiedsLanding-cardIcon" aria-hidden="true">
          <i className={icon} />
        </div>
        <div className="ClassifiedsLanding-cardBody">
          <span className="ClassifiedsLanding-cardTitle">{tag.name()}</span>
          {tag.description() ? (
            <span className="ClassifiedsLanding-cardSubtitle">{tag.description()}</span>
          ) : count ? (
            <span className="ClassifiedsLanding-cardSubtitle">
              {app.translator.trans('flarum-classifieds.forum.landing.discussion_count', { count })}
            </span>
          ) : null}
        </div>
      </a>
    );
  }
}
