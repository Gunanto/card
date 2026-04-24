<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaAssetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_dalam_institusi_sama_dapat_melihat_media_yang_diunggah_guru_lain(): void
    {
        $institution = Institution::query()->create(['name' => 'Inst A']);
        $guruA = User::factory()->guru($institution)->create();
        $guruB = User::factory()->guru($institution)->create();

        $asset = MediaAsset::query()->create([
            'owner_type' => 'institution',
            'owner_id' => $institution->id,
            'category' => 'institution_logo',
            'disk' => 'local',
            'bucket' => 'card-app',
            'object_key' => 'tests/'.Str::uuid().'.png',
            'original_name' => 'logo-inst-a.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1200,
            'uploaded_by' => $guruA->id,
        ]);

        $response = $this->actingAs($guruB)->get(route('media-assets.index'));

        $response->assertOk();
        $response->assertSee('logo-inst-a.png');
    }

    public function test_guru_beda_institusi_tidak_dapat_melihat_dan_mengakses_media(): void
    {
        $institutionA = Institution::query()->create(['name' => 'Inst A']);
        $institutionB = Institution::query()->create(['name' => 'Inst B']);
        $guruA = User::factory()->guru($institutionA)->create();
        $guruB = User::factory()->guru($institutionB)->create();

        $asset = MediaAsset::query()->create([
            'owner_type' => 'institution',
            'owner_id' => $institutionA->id,
            'category' => 'institution_logo',
            'disk' => 'local',
            'bucket' => 'card-app',
            'object_key' => 'tests/'.Str::uuid().'.png',
            'original_name' => 'logo-inst-a-private.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1200,
            'uploaded_by' => $guruA->id,
        ]);

        $indexResponse = $this->actingAs($guruB)->get(route('media-assets.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('logo-inst-a-private.png');

        $this->actingAs($guruB)
            ->get(route('media-assets.download', $asset))
            ->assertForbidden();

        $this->actingAs($guruB)
            ->get(route('media-assets.stream', $asset))
            ->assertForbidden();
    }
}
