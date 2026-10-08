import app from 'flarum/common/app';

let maps: any[] = [];
let request: Promise<any[]> | null = null;

/** The traced stadium charts a seller may pick, once loadSeatMaps() has run. */
export function seatMaps(): any[] {
  return maps;
}

/** Fetches the offered charts once per page load; a failure retries next time. */
export function loadSeatMaps(): Promise<any[]> {
  return (request ||= app.request<{ data: any[] }>({ method: 'GET', url: app.forum.attribute('apiUrl') + '/classifieds/seatmaps/offered' }).then(
    (r) => (maps = r.data || []),
    () => {
      request = null;
      return maps;
    }
  ));
}
