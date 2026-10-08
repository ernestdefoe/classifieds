import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';

import type { SeatMapSummary } from './SeatMapsPanel';

export interface SeatMapTracerAttrs extends IInternalModalAttrs {
  map: SeatMapSummary;
  onsave?: () => void;
}

type Point = { x: number; y: number };

/**
 * The tracer: type a section, click where it is, repeat.
 *
 * Click a placed marker to remove it. Nothing reaches the server until Save,
 * so a mis-click is undone by closing the modal.
 */
export default class SeatMapTracerModal extends Modal<SeatMapTracerAttrs> {
  sections: Record<string, Point> = {};
  loading = true;
  saving = false;
  name = '';

  oninit(vnode: Mithril.Vnode<SeatMapTracerAttrs, this>) {
    super.oninit(vnode);

    app
      .request<{ data: SeatMapSummary & { sections: Record<string, Point> } }>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/classifieds/seatmaps/' + this.attrs.map.id,
      })
      .then((result) => {
        this.sections = result.data.sections || {};
        this.loading = false;
        m.redraw();
      });
  }

  className() {
    return 'Modal--large ClassifiedsTracer-modal';
  }

  title() {
    return app.translator.trans('flarum-classifieds.admin.seatmaps.trace_title', { title: this.attrs.map.title });
  }

  content() {
    if (this.loading) {
      return (
        <div className="Modal-body">
          <LoadingIndicator />
        </div>
      );
    }

    const names = this.names();

    return (
      <div className="Modal-body ClassifiedsTracer">
        <p className="helpText">{app.translator.trans('flarum-classifieds.admin.seatmaps.trace_help')}</p>

        <div className="ClassifiedsTracer-controls">
          <input
            className="FormControl ClassifiedsTracer-name"
            type="text"
            value={this.name}
            placeholder={app.translator.trans('flarum-classifieds.admin.seatmaps.section_placeholder', {}, true) as string}
            oninput={(e: InputEvent) => (this.name = (e.target as HTMLInputElement).value)}
            onkeydown={(e: KeyboardEvent) => {
              // Enter must not submit the modal — the next action is a click on
              // the chart, and a closed modal loses every section placed so far.
              if (e.key === 'Enter') e.preventDefault();
            }}
          />
          <span className="ClassifiedsTracer-count">{app.translator.trans('flarum-classifieds.admin.seatmaps.traced', { count: names.length })}</span>
        </div>

        {/*
          🚨 The click listener is on the STAGE, not on the <img>.

          The marker overlay covers the picture edge to edge, so a click on the
          image never reaches the image — it lands on the overlay. Nothing
          errors; the tracer simply does not respond, which reads as a dead
          page. The overlay is pointer-events:none so the markers themselves
          stay clickable and nothing else intercepts.
        */}
        <div className="ClassifiedsTracer-canvas">
          <div className="ClassifiedsTracer-stage" onclick={(e: MouseEvent) => this.place(e)}>
            <img
              className="ClassifiedsTracer-image"
              src={this.attrs.map.image || ''}
              alt={this.attrs.map.title}
              oncreate={(vnode: Mithril.VnodeDOM) => (this.image = vnode.dom as HTMLImageElement)}
              onupdate={(vnode: Mithril.VnodeDOM) => (this.image = vnode.dom as HTMLImageElement)}
            />

            <div className="ClassifiedsTracer-markers" aria-hidden="true">
              {names.map((name) => (
                <button
                  type="button"
                  className="ClassifiedsTracer-marker"
                  key={name}
                  style={{ left: this.sections[name].x * 100 + '%', top: this.sections[name].y * 100 + '%' }}
                  title={app.translator.trans('flarum-classifieds.admin.seatmaps.remove', { section: name }, true) as string}
                  onclick={(e: MouseEvent) => {
                    e.preventDefault();
                    e.stopPropagation();
                    delete this.sections[name];
                  }}
                >
                  {name}
                </button>
              ))}
            </div>
          </div>
        </div>

        <div className="ClassifiedsTracer-footer">
          <Button className="Button Button--primary" loading={this.saving} disabled={this.saving} onclick={() => this.save()}>
            {app.translator.trans('flarum-classifieds.admin.seatmaps.save')}
          </Button>
        </div>
      </div>
    );
  }

  image?: HTMLImageElement;

  /** Sorted the way a stadium numbers its sections: 2, 10, 11 — not 10, 11, 2. */
  names(): string[] {
    return Object.keys(this.sections).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
  }

  place(e: MouseEvent) {
    const name = this.name.trim();

    if (!name) {
      /*
       * 🚨 Refuse rather than invent a name. An auto-numbered marker on the
       * wrong section is exactly the confidently-wrong star this whole feature
       * exists to avoid.
       */
      (this.element.querySelector('.ClassifiedsTracer-name') as HTMLInputElement | null)?.focus();
      return;
    }

    if (!this.image) return;

    /*
     * 🚨 Measured against the IMAGE, not the page or the stage.
     *
     * getBoundingClientRect on the rendered <img> gives the box the picture
     * actually occupies at this moment, so the fraction is right whatever the
     * zoom, the scroll position or the window width — and stays right when the
     * same chart is drawn much smaller on a phone later.
     */
    const box = this.image.getBoundingClientRect();
    const x = (e.clientX - box.left) / box.width;
    const y = (e.clientY - box.top) / box.height;

    if (x < 0 || x > 1 || y < 0 || y > 1) return;

    this.sections[name] = { x: Math.round(x * 10000) / 10000, y: Math.round(y * 10000) / 10000 };
    this.name = '';
    (this.element.querySelector('.ClassifiedsTracer-name') as HTMLInputElement | null)?.focus();
  }

  save() {
    if (this.saving) return;

    this.saving = true;

    app
      .request({
        method: 'PATCH',
        url: app.forum.attribute('apiUrl') + '/classifieds/seatmaps/' + this.attrs.map.id,
        body: { sections: this.sections },
      })
      .then(() => {
        this.saving = false;
        this.attrs.onsave?.();
        this.hide();
      })
      .catch(() => {
        this.saving = false;
        m.redraw();
      });
  }

  // The chart is traced by clicking, not by submitting; a stray Enter must not
  // close the modal and discard everything placed since it opened.
  onsubmit(e: Event) {
    e.preventDefault();
  }
}
