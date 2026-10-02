<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Support\SafeValueConverter;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['active' => true]);

    $this->linkField = CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => 'website',
        'name' => 'Website',
        'type' => 'link',
        'settings' => new CustomFieldSettingsData(allow_multiple: true, max_values: 5),
    ]);
});

it('strips the scheme from a link saved outside the panel form', function (): void {
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($this->linkField, ['https://example.com/pricing']);

    $stored = CustomFieldValue::query()->where('entity_id', $post->getKey())->where('custom_field_id', $this->linkField->getKey())->firstOrFail();

    expect(collect($stored->json_value)->all())->toBe(['example.com/pricing']);
});

it('collapses values that normalize to the same link', function (): void {
    expect(SafeValueConverter::toDbSafe(['https://example.com', 'http://example.com', 'example.com'], 'link', $this->linkField))
        ->toBe(['example.com']);
});

it('keeps numeric-looking values that differ as text', function (): void {
    expect(SafeValueConverter::toDbSafe(['007', '7', '1e1', '10'], 'link', $this->linkField))
        ->toBe(['007', '7', '1e1', '10']);
});

it('leaves values untouched when no field is given', function (): void {
    expect(SafeValueConverter::toDbSafe(['https://example.com'], 'link'))->toBe(['https://example.com']);
});

it('stores a phone saved outside the panel form as E.164', function (): void {
    $phoneField = CustomField::factory()->create([
        'custom_field_section_id' => $this->linkField->custom_field_section_id,
        'entity_type' => Post::class,
        'code' => 'phone',
        'name' => 'Phone',
        'type' => 'phone',
        'settings' => new CustomFieldSettingsData(allow_multiple: true, max_values: 5),
    ]);
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($phoneField, ['+1 (415) 555-0100', '+1-415-555-0100']);

    $stored = CustomFieldValue::query()->where('entity_id', $post->getKey())->where('custom_field_id', $phoneField->getKey())->firstOrFail();

    expect(collect($stored->json_value)->all())->toBe(['+14155550100']);
});

it('keeps a phone extension and stays stable when saved twice', function (): void {
    $phoneField = CustomField::factory()->create([
        'custom_field_section_id' => $this->linkField->custom_field_section_id,
        'entity_type' => Post::class,
        'code' => 'phone',
        'name' => 'Phone',
        'type' => 'phone',
        'settings' => new CustomFieldSettingsData(allow_multiple: true, max_values: 5),
    ]);
    $once = SafeValueConverter::toDbSafe(['+1 (415) 555-0100 ext. 12'], 'phone', $phoneField);

    expect($once)->toBe(['+14155550100;ext=12'])
        ->and(SafeValueConverter::toDbSafe($once, 'phone', $phoneField))->toBe($once);
});
