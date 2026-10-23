<?php

namespace Tests\Feature;

use App\Models\News\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $title = 'Kabar pertandingan'): array
    {
        return ['title' => $title, 'excerpt' => 'Ringkasan pertandingan terbaru.',
            'cover_image' => 'https://example.test/cover.jpg', 'category' => 'pertandingan',
            'content' => "Paragraf pertama.\n\nParagraf kedua."];
    }

    private function admin(): void
    {
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'email' => 'news@example.test', 'password' => 'test-password', 'is_admin' => true]), 'sanctum');
    }

    public function test_admin_save_is_immediately_public_without_any_status(): void
    {
        $this->admin();
        $id = $this->postJson('/api/news', [...$this->data(), 'status' => 'draft', 'is_admin' => true])
            ->assertCreated()->assertJsonMissingPath('status')->json('id');
        $this->getJson('/api/news')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonMissingPath('data.0.content');
        $this->getJson('/api/news/'.$id)->assertOk()->assertJsonPath('content', $this->data()['content'])->assertJsonMissingPath('status');
        $this->assertDatabaseCount('news', 1);
    }

    public function test_guest_and_member_cannot_create_edit_delete_or_upload_news_images(): void
    {
        $news = News::create($this->data());
        $this->postJson('/api/news', $this->data())->assertUnauthorized();
        $this->putJson('/api/news/'.$news->id, ['title' => 'Changed'])->assertUnauthorized();
        $this->deleteJson('/api/news/'.$news->id)->assertUnauthorized();
        $this->actingAs(User::forceCreate(['name' => 'Member', 'email' => 'member@example.test', 'password' => 'test-password', 'is_admin' => false]), 'sanctum');
        $this->postJson('/api/news', $this->data())->assertForbidden();
        $this->putJson('/api/news/'.$news->id, ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson('/api/news/'.$news->id)->assertForbidden();
        $this->postJson('/api/upload', ['type' => 'news'])->assertForbidden();
        $this->getJson('/api/news/'.$news->id)->assertOk();
        $this->assertSame('Kabar pertandingan', $news->fresh()->title);
    }

    public function test_edit_and_delete_are_reflected_in_public_feed_without_deleting_shared_cover(): void
    {
        Storage::fake('public'); Storage::disk('public')->put('news/shared.jpg', 'image');
        $this->admin(); $news = News::create($this->data());
        $this->putJson('/api/news/'.$news->id, ['title' => 'Judul baru', 'category' => 'kegiatan'])->assertOk()->assertJsonPath('title', 'Judul baru');
        $this->getJson('/api/news?category=kegiatan')->assertJsonCount(1, 'data');
        $this->deleteJson('/api/news/'.$news->id)->assertNoContent();
        $this->getJson('/api/news')->assertJsonCount(0, 'data');
        $this->getJson('/api/news/'.$news->id)->assertNotFound();
        Storage::disk('public')->assertExists('news/shared.jpg');
    }

    public function test_feed_is_newest_first_paginated_searchable_and_filterable(): void
    {
        foreach (['Satu', 'Dua', 'Tiga', 'Empat'] as $title) News::create($this->data($title));
        News::create([...$this->data('Pengumuman liga'), 'category' => 'pengumuman']);
        $this->getJson('/api/news?per_page=3')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Pengumuman liga')->assertJsonPath('total', 5);
        $this->getJson('/api/news?per_page=3&page=2')->assertJsonCount(2, 'data');
        $this->getJson('/api/news?category=pengumuman')->assertJsonCount(1, 'data');
        $this->getJson('/api/news?search=Tiga')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Tiga');
        $this->getJson('/api/news?per_page=5000')->assertUnprocessable();
        $this->getJson('/api/news?category=invalid')->assertUnprocessable();
    }

    public function test_invalid_content_and_unsafe_image_schemes_are_rejected(): void
    {
        $this->admin();
        $this->postJson('/api/news', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'excerpt', 'cover_image', 'category', 'content']);
        foreach (['javascript:alert(1)', 'data:image/png;base64,AAAA'] as $url) {
            $this->postJson('/api/news', [...$this->data(), 'cover_image' => $url])->assertUnprocessable()->assertJsonValidationErrors('cover_image');
        }
        $this->postJson('/api/news', [...$this->data(), 'title' => str_repeat('x', 181), 'category' => 'other'])->assertUnprocessable();
        $this->assertDatabaseCount('news', 0);
    }

    public function test_admin_can_choose_and_edit_news_date_and_feed_uses_it(): void
    {
        $this->admin();
        $id = $this->postJson('/api/news', [...$this->data(), 'news_date' => '2026-09-01'])
            ->assertCreated()->assertJsonPath('news_date', '2026-09-01')->json('id');
        $newer = $this->postJson('/api/news', [...$this->data('Berita lain'), 'news_date' => '2026-10-10'])
            ->assertCreated()->json('id');
        $this->getJson('/api/news')->assertJsonPath('data.0.id', $newer);
        $this->putJson('/api/news/'.$id, ['news_date' => '2026-10-20'])->assertOk();
        $this->getJson('/api/news')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.news_date', '2026-10-20');
        $this->getJson('/api/news/'.$id)->assertJsonPath('news_date', '2026-10-20');
        $this->putJson('/api/news/'.$id, ['news_date' => '2026-02-30'])->assertUnprocessable();
        $this->postJson('/api/news', [...$this->data(), 'news_date' => ''])->assertUnprocessable();
    }

    public function test_gallery_limit_cover_order_edit_and_legacy_compatibility(): void
    {
        $this->admin();
        $images = ['https://example.test/1.jpg', 'https://example.test/2.png', 'https://example.test/3.webp'];
        $payload = $this->data(); unset($payload['cover_image']);
        $id = $this->postJson('/api/news', [...$payload, 'images' => $images])->assertCreated()
            ->assertJsonPath('cover_image', $images[0])->assertJsonPath('images', $images)->json('id');
        $this->getJson('/api/news/'.$id)->assertJsonPath('images', $images);
        $this->putJson('/api/news/'.$id, ['images' => [$images[2], $images[0]]])->assertOk()->assertJsonPath('cover_image', $images[2]);
        $this->putJson('/api/news/'.$id, ['title' => 'Edit teks'])->assertOk()->assertJsonCount(2, 'images');
        $this->getJson('/api/news')->assertJsonPath('data.0.cover_image', $images[2]);
        foreach ([[], [...$images, 'https://example.test/4.jpg'], [$images[0], $images[0]], ['javascript:alert(1)']] as $bad) {
            $this->putJson('/api/news/'.$id, ['images' => $bad])->assertUnprocessable();
        }
        $legacy = News::create($this->data());
        $this->putJson('/api/news/'.$legacy->id, ['title' => 'Lama'])->assertOk()->assertJsonPath('cover_image', $this->data()['cover_image']);
        $this->putJson('/api/news/'.$id, ['cover_image' => $images[1]])->assertOk()->assertJsonPath('images', [$images[1]]);
    }

    public function test_news_cover_upload_is_three_megabytes_and_uses_separate_folder(): void
    {
        $this->admin(); Storage::fake('public');
        $make = fn () => UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHf0AAAAASUVORK5CYII='));
        $file = $make()->size(3072);
        $response = $this->postJson('/api/upload', ['file' => $file, 'type' => 'news'])->assertCreated();
        $this->assertStringStartsWith('news/', $response->json('path'));
        Storage::disk('public')->assertExists($response->json('path'));
        $this->postJson('/api/upload', ['file' => $make()->size(3073), 'type' => 'news'])->assertUnprocessable();
    }
}
