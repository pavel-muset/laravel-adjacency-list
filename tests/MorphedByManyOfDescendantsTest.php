<?php

namespace Tests;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\BelongsToManyOfDescendants;
use Tests\Models\Video;
use Tests\Models\User;
use Tests\Scopes\TestScope;

class MorphedByManyOfDescendantsTest extends TestCase
{
    public function testLazyLoading()
    {
        $videos = User::find(2)->videos;

        $this->assertEquals([53, 83], $videos->pluck('id')->all());
    }

    public function testLazyLoadingAndSelf()
    {
        $videos = User::find(2)->videosAndSelf;

        $this->assertEquals([23, 53, 83], $videos->pluck('id')->all());
    }

    public function testEagerLoading()
    {
        $users = User::with(['videos' => function (BelongsToManyOfDescendants $query) {
            $query->orderBy('id');
        }])->get();

        $this->assertEquals([23, 33, 43, 53, 63, 73, 83], $users[0]->videos->pluck('id')->all());
        $this->assertEquals([53, 83], $users[1]->videos->pluck('id')->all());
        $this->assertEquals([], $users[8]->videos->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videos->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videos[0]);
    }

    public function testEagerLoadingAndSelf()
    {
        $users = User::with(['videosAndSelf' => function (BelongsToManyOfDescendants $query) {
            $query->orderBy('id');
        }])->get();

        $this->assertEquals([13, 23, 33, 43, 53, 63, 73, 83], $users[0]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([23, 53, 83], $users[1]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([], $users[8]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videosAndSelf->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videosAndSelf[0]);
    }

    public function testLazyEagerLoading()
    {
        $users = User::all()->load(['videos' => function (BelongsToManyOfDescendants $query) {
            $query->orderBy('id');
        }]);

        $this->assertEquals([23, 33, 43, 53, 63, 73, 83], $users[0]->videos->pluck('id')->all());
        $this->assertEquals([53, 83], $users[1]->videos->pluck('id')->all());
        $this->assertEquals([], $users[8]->videos->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videos->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videos[0]);
    }

    public function testLazyEagerLoadingAndSelf()
    {
        $users = User::all()->load(['videosAndSelf' => function (BelongsToManyOfDescendants $query) {
            $query->orderBy('id');
        }]);

        $this->assertEquals([13, 23, 33, 43, 53, 63, 73, 83], $users[0]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([23, 53, 83], $users[1]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([], $users[8]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videosAndSelf->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videosAndSelf[0]);
    }

    public function testExistenceQuery()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $this->markTestSkipped();
        }

        $users = User::find(8)->ancestors()->has('videos', '>', 1)->get();

        $this->assertEquals([2, 1], $users->pluck('id')->all());
    }

    public function testExistenceQueryAndSelf()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $this->markTestSkipped();
        }

        $users = User::find(8)->ancestors()->has('videosAndSelf', '>', 2)->get();

        $this->assertEquals([2, 1], $users->pluck('id')->all());
    }

    public function testExistenceQueryForSelfRelation()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $this->markTestSkipped();
        }

        $users = User::has('videos', '>', 1)->get();

        $this->assertEquals([1, 2, 11], $users->pluck('id')->all());
    }

    public function testExistenceQueryForSelfRelationAndSelf()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $this->markTestSkipped();
        }

        $users = User::has('videosAndSelf', '>', 2)->get();

        $this->assertEquals([1, 2], $users->pluck('id')->all());
    }

    public function testDelete()
    {
        $affected = User::find(1)->videos()->delete();

        $this->assertEquals(7, $affected);
        $this->assertNotNull(Video::withTrashed()->find(83)->deleted_at);
        $this->assertNull(Video::find(13)->deleted_at);
    }

    public function testDeleteAndSelf()
    {
        $affected = User::find(1)->videosAndSelf()->delete();

        $this->assertEquals(8, $affected);
        $this->assertNotNull(Video::withTrashed()->find(83)->deleted_at);
        $this->assertNotNull(Video::withTrashed()->find(13)->deleted_at);
    }

    public function testWithTrashedDescendants()
    {
        $videos = User::find(4)->videos()->withTrashedDescendants()->get();

        $this->assertEquals([73, 93], $videos->pluck('id')->all());
    }

    public function testWithIntermediateScope()
    {
        $videos = User::find(2)->videos()->withIntermediateScope('test', new TestScope())->get();

        $this->assertEquals([53], $videos->pluck('id')->all());
    }

    public function testWithoutIntermediateScope()
    {
        $videos = User::find(2)->videos()
            ->withIntermediateScope('test', new TestScope())
            ->withoutIntermediateScope('test')
            ->get();

        $this->assertEquals([53, 83], $videos->pluck('id')->all());
    }

    public function testWithoutIntermediateScopeWithObject()
    {
        $videos = User::find(4)->videos()->withoutIntermediateScope(new SoftDeletingScope())->get();

        $this->assertEquals([73, 93], $videos->pluck('id')->all());
    }

    public function testWithoutIntermediateScopes()
    {
        $videos = User::find(2)->videos()
            ->withIntermediateScope('test', new TestScope())
            ->withoutIntermediateScopes()
            ->get();

        $this->assertEquals([53, 83], $videos->pluck('id')->all());
    }

    public function testIntermediateScopes()
    {
        $relationship = User::find(2)->videos()->withIntermediateScope('test', new TestScope());

        $this->assertArrayHasKey('test', $relationship->intermediateScopes());
    }

    public function testRemovedIntermediateScopes()
    {
        $relationship = User::find(2)->videos()
            ->withIntermediateScope('test', new TestScope())
            ->withoutIntermediateScope('test');

        $this->assertSame(['test'], $relationship->removedIntermediateScopes());
    }
}
