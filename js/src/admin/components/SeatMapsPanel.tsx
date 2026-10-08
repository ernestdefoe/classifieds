import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';

import SeatMapTracerModal from './SeatMapTracerModal';

export interface SeatMapSummary {
  id: number;
  title: string;
  image: string | null;
  width: number;
  height: number;
  sectionCount: number;
}

/**
 * Stadium seating charts, and the tracing that makes them useful.
 *
 * 🚨 There is no source of stadium section geometry, so this exists.
 *
 * Putting a star on a chart needs, per ground, where every section sits on one
 * specific image. Nothing publishes that in a usable form: the charts online
 * are pictures, and the interactive ones on ticket sites are proprietary.
 * Section numbering is not standardised either, so a generic bowl diagram
 * would put the star on the wrong side of the ground often enough to mislead a
 * buyer. The coordinates are therefore entered once, by hand, per ground.
 *
 * Ten or fifteen venues cover most of a board's traffic; everything else falls
 * back to the seller's own text, which is what a listing shows today.
 */
export default class SeatMapsPanel extends Component<ComponentAttrs> {
  maps: SeatMapSummary[] | null = null;
  title = '';
  url = '';
  file: File | null = null;
  busy = false;
  error: string | null = null;

  oninit(vnode: Mithril.Vnode<ComponentAttrs, this>) {
    super.oninit(vnode);
    this.load();
  }

  view() {
    return (
      <div className="ClassifiedsSeatMaps">
        <h3>{this.t('title')}</h3>
        <p className="helpText">{this.t('help')}</p>

        {this.maps === null ? <LoadingIndicator /> : this.list()}

        <fieldset className="ClassifiedsSeatMaps-add">
          <legend>{this.t('add')}</legend>

          <input
            className="FormControl"
            type="text"
            maxlength={100}
            value={this.title}
            placeholder={this.t('name_placeholder', true)}
            oninput={(e: InputEvent) => (this.title = (e.target as HTMLInputElement).value)}
          />

          <div className="ClassifiedsSeatMaps-sources">
            <label className="ClassifiedsSeatMaps-file">
              <span>{this.t('upload')}</span>
              <input
                type="file"
                accept="image/png,image/jpeg,image/gif,image/webp"
                onchange={(e: Event) => {
                  this.file = (e.target as HTMLInputElement).files?.[0] || null;
                  // A file and a URL are two ways to do one thing; holding both
                  // would leave which one wins up to the order of an if.
                  if (this.file) this.url = '';
                }}
              />
            </label>

            <span className="ClassifiedsSeatMaps-or">{this.t('or')}</span>

            <input
              className="FormControl"
              type="url"
              value={this.url}
              placeholder="https://…/seating-chart.pdf"
              oninput={(e: InputEvent) => {
                this.url = (e.target as HTMLInputElement).value;
                if (this.url) this.file = null;
              }}
            />
          </div>

          <p className="helpText">{this.t('url_help')}</p>

          {this.error && <p className="ClassifiedsSeatMaps-error">{this.error}</p>}

          <Button className="Button Button--primary" loading={this.busy} disabled={this.busy} onclick={() => this.add()}>
            {this.t('add_button')}
          </Button>
        </fieldset>
      </div>
    );
  }

  list() {
    if (!this.maps!.length) {
      return <p className="ClassifiedsSeatMaps-empty">{this.t('none')}</p>;
    }

    return (
      <ul className="ClassifiedsSeatMaps-list">
        {this.maps!.map((map) => (
          <li className="ClassifiedsSeatMaps-item" key={map.id}>
            {map.image && <img className="ClassifiedsSeatMaps-thumb" src={map.image} alt="" loading="lazy" />}

            <div className="ClassifiedsSeatMaps-meta">
              <strong>{map.title}</strong>
              {/*
                🚨 An untraced chart is called out, not merely counted as zero.
                It is invisible to sellers until it has at least one section on
                it, and an operator who uploaded one an hour ago needs to know
                that is why nobody can pick it.
              */}
              <span className={'ClassifiedsSeatMaps-count' + (map.sectionCount ? '' : ' ClassifiedsSeatMaps-count--none')}>
                {map.sectionCount
                  ? app.translator.trans('flarum-classifieds.admin.seatmaps.traced', { count: map.sectionCount })
                  : this.t('untraced')}
              </span>
            </div>

            <Button className="Button Button--small" onclick={() => this.trace(map)}>
              {this.t('trace')}
            </Button>
            <Button className="Button Button--small Button--danger" onclick={() => this.remove(map)}>
              {this.t('delete')}
            </Button>
          </li>
        ))}
      </ul>
    );
  }

  /** A message as children, or with raw, as plain text for attributes and errors. */
  t(key: string): any[];
  t(key: string, raw: true): string;
  t(key: string, raw = false) {
    const id = `flarum-classifieds.admin.seatmaps.${key}`;

    return raw ? app.translator.trans(id, {}, true) : app.translator.trans(id, {});
  }

  endpoint(suffix = '') {
    return app.forum.attribute('apiUrl') + '/classifieds/seatmaps' + suffix;
  }

  load() {
    app.request<{ data: SeatMapSummary[] }>({ method: 'GET', url: this.endpoint() }).then((result) => {
      this.maps = result.data;
      m.redraw();
    });
  }

  add() {
    if (this.busy) return;

    this.error = null;

    if (!this.title.trim()) {
      this.error = this.t('needs_title', true);
      return;
    }

    if (!this.file && !this.url.trim()) {
      this.error = this.t('needs_image', true);
      return;
    }

    this.busy = true;

    /*
     * 🚨 Sent as FormData either way, upload or URL.
     *
     * A file cannot travel as JSON, and having two request shapes for one
     * action means two paths to keep in step — the one used less often is the
     * one that quietly rots.
     */
    const body = new FormData();
    body.append('title', this.title.trim());
    if (this.file) body.append('file', this.file);
    else body.append('url', this.url.trim());

    app
      .request<{ data: SeatMapSummary }>({
        method: 'POST',
        url: this.endpoint(),
        serialize: (raw: any) => raw,
        body,
      })
      .then((result) => {
        this.title = '';
        this.url = '';
        this.file = null;
        this.busy = false;
        this.load();
        // Straight into the tracer: a chart nobody traces is a chart nobody
        // can choose, so the next step is never left to be remembered later.
        this.trace(result.data);
      })
      .catch((e: any) => {
        this.busy = false;
        this.error = this.reason(e);
        m.redraw();
      });
  }

  /**
   * 🚨 The server returns a key, and it is translated here.
   *
   * A sentence from the server would ship untranslated English into every
   * installation, and a new failure mode would arrive as a raw string with no
   * way for a translator to reach it.
   */
  reason(e: any): string {
    const payload = e?.response || {};
    const key = payload.error || 'fetch_failed';
    const detail = payload.detail ? ` (${payload.detail})` : '';

    return (app.translator.trans(`flarum-classifieds.admin.seatmaps.errors.${key}`, {}, true) as string) + detail;
  }

  trace(map: SeatMapSummary) {
    app.modal.show(SeatMapTracerModal, { map, onsave: () => this.load() });
  }

  remove(map: SeatMapSummary) {
    if (!confirm(app.translator.trans('flarum-classifieds.admin.seatmaps.delete_confirm', { title: map.title }, true) as string)) {
      return;
    }

    app.request({ method: 'DELETE', url: this.endpoint('/' + map.id) }).then(() => this.load());
  }
}
