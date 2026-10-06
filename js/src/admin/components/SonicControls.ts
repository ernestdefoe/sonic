import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';

const K = 'ernestdefoe-sonic';
const t = (k: string, p?: Record<string, unknown>) => app.translator.trans(`${K}.admin.${k}`, p);

// Core keeps each resource's driver under `search_driver_<ModelClass>`.
const DRIVERS: [string, string][] = [
  ['Flarum\\Discussion\\Discussion', 'discussions'],
  ['Flarum\\Post\\Post', 'posts'],
  ['Flarum\\User\\User', 'users'],
];

interface Status {
  ok: boolean;
  error?: string;
  counts?: Record<string, number>;
}

interface Attrs {
  setting: (k: string, d?: string) => (v?: string) => string;
}

export default class SonicControls extends Component<Attrs> {
  status: Status | null = null;
  checking = false;
  rebuilding = false;

  view() {
    const setting = this.attrs.setting;
    // The saved password never reaches the browser (see HidePassword); the
    // field only ever writes a new one.
    const password = setting(`${K}.password`, '');
    const saved = !!app.data.settings[`${K}.password_set`];

    // .Form-body takes the settings form's own 24px gap between groups.
    return m('.Form-body', [
      m('.Form-group', [
        m('label', t('password_label')),
        m('.helpText', t('password_help')),
        m('input.FormControl', {
          type: 'password',
          autocomplete: 'new-password',
          value: password(),
          placeholder: saved ? t('password_saved') : '',
          oninput: (e: InputEvent) => password((e.target as HTMLInputElement).value),
        }),
      ]),

      m('.Form-group', [
        m('label', t('drivers_label')),
        m('.helpText', t('drivers_help')),
        DRIVERS.map(([model, name]) => {
          const driver = setting(`search_driver_${model}`, 'default');
          return m(
            Switch,
            { state: driver() === 'sonic', onchange: (on: boolean) => driver(on ? 'sonic' : 'default') },
            t(`use_for_${name}`)
          );
        }),
      ]),

      m('.Form-group', [
        m('label', t('status_label')),
        m(Button, { className: 'Button', loading: this.checking, onclick: () => this.check() }, t('status_button')),
        this.status && m('.helpText', this.statusText(this.status)),
      ]),

      m('.Form-group', [
        m('label', t('rebuild_label')),
        m('.helpText', t('rebuild_help')),
        m(Button, { className: 'Button', loading: this.rebuilding, onclick: () => this.rebuild() }, t('rebuild_button')),
      ]),
    ]);
  }

  statusText(s: Status) {
    if (!s.ok) return t(`error.${s.error || 'unreachable'}`);
    const c = s.counts || {};
    return t('status_ok', { discussions: c.discussions ?? 0, posts: c.posts ?? 0, users: c.users ?? 0 });
  }

  url(path: string) {
    return `${app.forum.attribute('apiUrl')}/sonic/${path}`;
  }

  check() {
    this.checking = true;
    app
      .request<Status>({ method: 'GET', url: this.url('status') })
      .then((s) => (this.status = s), () => (this.status = { ok: false }))
      .finally(() => {
        this.checking = false;
        m.redraw();
      });
  }

  rebuild() {
    this.rebuilding = true;
    app
      .request({ method: 'POST', url: this.url('rebuild') })
      .then(
        () => app.alerts.show({ type: 'success' }, t('rebuild_queued')),
        () => app.alerts.show({ type: 'error' }, t('rebuild_failed'))
      )
      .finally(() => {
        this.rebuilding = false;
        m.redraw();
      });
  }
}
