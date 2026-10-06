import app from 'flarum/admin/app';
import Admin from 'flarum/common/extenders/Admin';
import SonicControls from './components/SonicControls';

const K = 'ernestdefoe-sonic';
const t = (k: string) => app.translator.trans(`${K}.admin.${k}`);

export default [
  new Admin()
    .setting(() => ({ setting: `${K}.host`, type: 'text', label: t('host_label'), help: t('host_help') }))
    .setting(() => ({ setting: `${K}.port`, type: 'number', min: 1, max: 65535, label: t('port_label') }))
    .setting(() => ({ setting: `${K}.bucket`, type: 'text', label: t('bucket_label'), help: t('bucket_help') }))
    .customSetting(function (this: { setting: (k: string, d?: string) => (v?: string) => string }) {
      return m(SonicControls, { setting: (k: string, d?: string) => this.setting(k, d) });
    }),
];
