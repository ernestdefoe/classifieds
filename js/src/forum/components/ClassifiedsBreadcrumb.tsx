import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';

export interface ClassifiedsBreadcrumbAttrs extends ComponentAttrs {
  discussion: Discussion;
}

export default class ClassifiedsBreadcrumb extends Component<ClassifiedsBreadcrumbAttrs> {
  view(): Mithril.Children {
    const discussion = this.attrs.discussion;
    const tags: any[] = (discussion as any).tags?.() || [];

    const crumbs: Array<{ label: string; href?: string }> = [];

    tags
      .filter((t: any) => t && typeof t.isClassifieds === 'function' && t.isClassifieds())
      .forEach((tag: any) => {
        crumbs.push({
          label: tag.name?.(),
          href: app.route('tag', { tags: tag.slug?.() }),
        });
      });

    crumbs.push({ label: discussion.title() });

    if (crumbs.length === 0) return null;

    return (
      <nav className="ClassifiedsBreadcrumb" aria-label="Breadcrumb">
        <ol className="ClassifiedsBreadcrumb-list">
          {crumbs.map((c, i) => (
            <li key={i} className="ClassifiedsBreadcrumb-item">
              {c.href ? (
                <a className="ClassifiedsBreadcrumb-link" href={c.href} config={(m as any).route.link}>
                  {c.label}
                </a>
              ) : (
                <span className="ClassifiedsBreadcrumb-current">{c.label}</span>
              )}
              {i < crumbs.length - 1 && (
                <i className="fas fa-chevron-right ClassifiedsBreadcrumb-sep" aria-hidden="true" />
              )}
            </li>
          ))}
        </ol>
      </nav>
    );
  }
}
