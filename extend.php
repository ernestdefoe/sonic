<?php

use Ernestdefoe\Sonic\Api\Controller\RebuildController;
use Ernestdefoe\Sonic\Api\Controller\StatusController;
use Ernestdefoe\Sonic\Console\IndexCommand;
use Ernestdefoe\Sonic\Listener\HidePassword;
use Ernestdefoe\Sonic\Provider\SearchProvider;
use Ernestdefoe\Sonic\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Sonic\Search\Discussion\FulltextFilter as DiscussionFulltextFilter;
use Ernestdefoe\Sonic\Search\Discussion\PostReindexer;
use Ernestdefoe\Sonic\Search\Discussion\SonicDiscussionSearcher;
use Ernestdefoe\Sonic\Search\Post\FulltextFilter as PostFulltextFilter;
use Ernestdefoe\Sonic\Search\Post\PostIndexer;
use Ernestdefoe\Sonic\Search\Post\SonicPostSearcher;
use Ernestdefoe\Sonic\Search\SonicSearchDriver;
use Ernestdefoe\Sonic\Search\User\FulltextFilter as UserFulltextFilter;
use Ernestdefoe\Sonic\Search\User\SonicUserSearcher;
use Ernestdefoe\Sonic\Search\User\UserIndexer;
use Ernestdefoe\Sonic\Sonic;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema\Attribute;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\Event\Deserializing;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->default(Sonic::KEY.'.host', '127.0.0.1')
        ->default(Sonic::KEY.'.port', '1491'),

    // Which search tabs Sonic answers, for the search modal's badge. Resource
    // names only: the host and password never leave the server.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Attribute::make('sonicSearch')->get(function () {
                $settings = resolve(SettingsRepositoryInterface::class);

                return array_keys(array_filter(
                    ['discussions' => Discussion::class, 'users' => User::class, 'posts' => Post::class],
                    fn (string $model) => $settings->get("search_driver_$model") === SonicSearchDriver::name()
                ));
            }),
        ]),

    (new Extend\SearchDriver(SonicSearchDriver::class))
        ->addSearcher(Discussion::class, SonicDiscussionSearcher::class)
        ->setFulltext(SonicDiscussionSearcher::class, DiscussionFulltextFilter::class)
        ->addSearcher(User::class, SonicUserSearcher::class)
        ->setFulltext(SonicUserSearcher::class, UserFulltextFilter::class)
        ->addSearcher(Post::class, SonicPostSearcher::class)
        ->setFulltext(SonicPostSearcher::class, PostFulltextFilter::class),

    // 🚨 Registered on CommentPost, not Post. Core observes the model class
    // each indexer is keyed by, and Eloquent fires a reply's events as
    // "eloquent.created: Flarum\Post\CommentPost" — keyed on Post, no reply,
    // edit, hide or delete would ever reach the index.
    (new Extend\SearchIndex())
        ->indexer(Discussion::class, DiscussionIndexer::class)
        ->indexer(CommentPost::class, PostReindexer::class)
        ->indexer(CommentPost::class, PostIndexer::class)
        ->indexer(User::class, UserIndexer::class),

    new Extend\ServiceProvider(SearchProvider::class),

    (new Extend\Event())
        ->listen(Deserializing::class, HidePassword::class),

    (new Extend\Console())
        ->command(IndexCommand::class),

    (new Extend\Routes('api'))
        ->get('/sonic/status', 'ernestdefoe-sonic.status', StatusController::class)
        ->post('/sonic/rebuild', 'ernestdefoe-sonic.rebuild', RebuildController::class),
];
