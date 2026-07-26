<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class TagController extends Controller
{
    use AuthorizesCompany;

    /**
     * Search tags for autocomplete (debounced on the client).
     */
    public function search(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $q = trim((string) $request->query('q', ''));
        $query = Tag::query()->where('company_id', $company->id);

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where('name', 'like', $like);
        }

        $tags = $query->orderBy('name')->limit(25)->get(['id', 'name']);

        return response()->json(['data' => $tags]);
    }

    /**
     * Create a tag (or return existing by normalized name).
     */
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $tag = Tag::findOrCreateForCompany($company->id, $data['name']);

        return response()->json([
            'tag' => ['id' => $tag->id, 'name' => $tag->name],
        ]);
    }

    /**
     * List tags attached to a property (JSON API).
     */
    public function forProperty(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->assertPropertyBelongsToCompany($company, $property);

        $tags = $property->tags()->get(['tags.id', 'tags.name']);

        return response()->json(['data' => $tags]);
    }

    /**
     * Replace all tags on a property with the given set of tag IDs.
     */
    public function sync(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->assertPropertyBelongsToCompany($company, $property);

        $data = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer', 'distinct'],
        ]);

        $ids = collect($data['tag_ids'])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $validIds = Tag::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->pluck('id');

        $property->tags()->sync($validIds);

        return response()->json([
            'message' => 'Tags updated.',
            'data' => $property->tags()->get(['tags.id', 'tags.name']),
        ]);
    }

    /**
     * Rename a tag globally for the company. Since tags are attached to
     * properties by ID (via the `property_tag` pivot — see Tag::properties()),
     * renaming here automatically reflects everywhere that tag is already
     * attached — no separate cascade update needed (unlike the plain-string
     * Province/Owner Name lists).
     */
    public function update(Request $request, Company $company, Tag $tag)
    {
        $this->authorizeCompany($company);
        abort_unless($tag->company_id === $company->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $name = Str::limit(trim($data['name']), 150, '');
        $normalized = Str::lower($name);

        $duplicate = Tag::where('company_id', $company->id)
            ->where('normalized_name', $normalized)
            ->where('id', '!=', $tag->id)
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'A tag with that name already exists.'], 422);
        }

        $tag->update(['name' => $name]);

        return response()->json(['tag' => ['id' => $tag->id, 'name' => $tag->name]]);
    }

    /**
     * Delete a tag globally for the company. The pivot rows on
     * `property_tag` cascade-delete with it (see the tags/property_tag
     * migration), so it's removed from every property it was attached to.
     */
    public function destroy(Company $company, Tag $tag)
    {
        $this->authorizeCompany($company);
        abort_unless($tag->company_id === $company->id, 404);

        $tag->delete();

        return response()->json(['message' => 'Tag deleted.']);
    }

    private function assertPropertyBelongsToCompany(Company $company, Property $property): void
    {
        if ((int) $property->company_id !== (int) $company->id) {
            abort(404);
        }
    }
}
