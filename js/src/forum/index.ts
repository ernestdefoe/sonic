import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

const K = 'ernestdefoe-sonic';

app.initializers.add(K, () => {
  // SearchModal is a lazy chunk, so it is extended by module path; core
  // applies this once the chunk loads.
  extend('flarum/common/components/SearchModal', 'activeTabItems', function (this: any, items: ItemList<Mithril.Children>) {
    const resource = this.activeSource?.()?.resource;

    if (resource && (app.forum.attribute<string[]>('sonicSearch') || []).includes(resource)) {
      items.add('sonic', m('.SearchModal-section.SonicBadge', app.translator.trans(`${K}.forum.powered_by`)), 0);
    }
  });
});
