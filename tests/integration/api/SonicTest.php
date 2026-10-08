<?php

namespace Ernestdefoe\Sonic\Tests\integration\api;

use Carbon\Carbon;
use Ernestdefoe\Sonic\Sonic;
use Ernestdefoe\Sonic\Tests\integration\FakeSonic;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

class SonicTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-sonic');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Discussion::class => [
                ['id' => 1, 'title' => 'Lighthouse keeping', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'last_post_number' => 1],
                ['id' => 2, 'title' => 'Lighthouse secrets', 'created_at' => Carbon::now(), 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'last_post_number' => 1, 'hidden_at' => Carbon::now()],
                ['id' => 3, 'title' => 'Lighthouse lamps', 'created_at' => Carbon::now()->subDay(), 'last_posted_at' => Carbon::now()->subDay(), 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'last_post_number' => 1],
                ['id' => 4, 'title' => 'Something else', 'created_at' => Carbon::now(), 'user_id' => 1, 'first_post_id' => 4, 'comment_count' => 1, 'last_post_number' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>One</p></t>'],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Two</p></t>'],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Three</p></t>'],
                ['id' => 4, 'discussion_id' => 4, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Four</p></t>'],
            ],
        ]);
    }

    private function fake(): FakeSonic
    {
        $container = $this->app()->getContainer();
        $fake = $container->make(FakeSonic::class);
        $container->instance(Sonic::class, $fake);

        return $fake;
    }

    private function json(string $method, string $path, ?int $actor = null, array $options = []): array
    {
        $request = $this->request($method, $path, $options + ($actor ? ['authenticatedAs' => $actor] : []));
        if ($method !== 'GET' && ! $actor) {
            $request = $this->withGuestSession($request);
        }
        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** A guest's write needs a session and its CSRF token, as a browser has. */
    private function withGuestSession(ServerRequestInterface $request): ServerRequestInterface
    {
        $initial = $this->send($this->request('GET', '/api'));

        return $this->requestWithCookiesFrom($request->withHeader('X-CSRF-Token', $initial->getHeaderLine('X-CSRF-Token')), $initial);
    }

    /** @return list<string> discussion ids a fulltext search returns, in order */
    private function search(string $q, ?int $actor = null): array
    {
        $response = $this->send($this->request('GET', '/api/discussions', $actor ? ['authenticatedAs' => $actor] : [])->withQueryParams(['filter' => ['q' => $q]]));
        $this->assertSame(200, $response->getStatusCode());

        return array_column(json_decode((string) $response->getBody(), true)['data'], 'id');
    }

    #[Test]
    public function only_an_admin_can_check_the_connection_or_start_a_rebuild()
    {
        foreach ([null, 2] as $actor) {
            [$status] = $this->json('GET', '/api/sonic/status', $actor);
            $this->assertSame(403, $status);
            [$status] = $this->json('POST', '/api/sonic/rebuild', $actor);
            $this->assertSame(403, $status);
        }
    }

    #[Test]
    public function the_status_reports_an_unreachable_server_without_throwing()
    {
        // Nothing listens on this port.
        $this->setting('ernestdefoe-sonic.port', '1');

        [$status, $body] = $this->json('GET', '/api/sonic/status', 1);
        $this->assertSame(200, $status);
        $this->assertFalse($body['ok']);
        $this->assertArrayHasKey('error', $body);
    }

    #[Test]
    public function an_unconfigured_server_is_said_so_and_never_rebuilt()
    {
        $this->setting('ernestdefoe-sonic.host', '');

        [, $body] = $this->json('GET', '/api/sonic/status', 1);
        $this->assertSame(['ok' => false, 'error' => 'not_configured'], $body);

        [$status, $body] = $this->json('POST', '/api/sonic/rebuild', 1);
        $this->assertSame(422, $status);
        $this->assertSame('not_configured', $body['error']);
    }

    #[Test]
    public function search_keeps_sonics_order_and_never_returns_what_the_actor_cannot_see()
    {
        $this->setting('search_driver_'.Discussion::class, 'sonic');
        $sonic = $this->fake();
        $sonic->hits = [3, 2, 1];

        $this->assertSame(['3', '1'], $this->search('lighthouse'), 'Sonic\'s order, though 1 is the more recent; 4 is visible but not a hit');
        $this->assertSame(['3', '2', '1'], $this->search('lighthouse', 1));
        $this->assertSame(['discussions', 'discussions'], $sonic->searched);
    }

    #[Test]
    public function a_server_that_cannot_answer_falls_back_to_the_database()
    {
        $this->setting('search_driver_'.Discussion::class, 'sonic');
        $sonic = $this->fake();
        $sonic->hits = null;

        // The database driver matches with FULLTEXT on MySQL and MariaDB, and
        // InnoDB's FULLTEXT index does not see rows a still-open transaction
        // wrote, which is how every test's fixtures are inserted.
        if (in_array($this->database()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('InnoDB FULLTEXT cannot see uncommitted fixtures.');
        }

        $this->assertEqualsCanonicalizing(['1', '3'], $this->search('lighthouse'));
    }

    #[Test]
    public function the_forum_says_which_searches_sonic_answers_and_never_the_password()
    {
        $this->setting('ernestdefoe-sonic.password', 'hunter2');
        $this->setting('search_driver_'.Post::class, 'sonic');

        [, $body] = $this->json('GET', '/api');
        $this->assertSame(['posts'], $body['data']['attributes']['sonicSearch']);
        $this->assertStringNotContainsString('hunter2', json_encode($body));
    }

    #[Test]
    public function the_password_is_write_only_in_the_admin()
    {
        $this->setting('ernestdefoe-sonic.password', 'hunter2');
        $this->config('debug', false);

        $html = (string) $this->send($this->request('GET', '/admin', ['authenticatedAs' => 1]))->getBody();
        preg_match('#<script id="flarum-json-payload" type="application/json">(.*?)</script>#s', $html, $m);
        $settings = json_decode($m[1], true)['settings'];

        $this->assertArrayNotHasKey('ernestdefoe-sonic.password', $settings);
        $this->assertTrue($settings['ernestdefoe-sonic.password_set']);
        $this->assertStringNotContainsString('hunter2', $html);

        // Saving the settings page with the field left blank keeps it.
        [$status] = $this->json('POST', '/api/settings', 1, ['json' => ['ernestdefoe-sonic.password' => '', 'ernestdefoe-sonic.host' => '10.0.0.5']]);
        $this->assertSame(204, $status);
        $settingsRepo = $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
        $this->assertSame('hunter2', $settingsRepo->get('ernestdefoe-sonic.password'));
        $this->assertSame('10.0.0.5', $settingsRepo->get('ernestdefoe-sonic.host'));
    }

    #[Test]
    public function a_reply_reaches_the_index_and_a_down_server_never_breaks_it()
    {
        $sonic = $this->fake();

        [$status] = $this->json('POST', '/api/posts', 2, ['json' => ['data' => [
            'type' => 'posts',
            'attributes' => ['content' => 'A reply about lamps'],
            'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]],
        ]]]);

        $this->assertSame(201, $status, 'The server is down; posting still works');
        $this->assertGreaterThanOrEqual(2, $sonic->ingests, 'The post index and its discussion\'s object were both asked for');
    }
}
