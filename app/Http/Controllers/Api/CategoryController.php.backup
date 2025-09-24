<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();

            // Get categories that the user has selected
            $categories = Category::whereIn('id', function($query) use ($user) {
                    $query->select('category_id')
                          ->from('user_categories')
                          ->where('user_id', $user->id)
                          ->where('is_active', true);
                })
                ->with('children')
                ->whereNull('parent_id')
                ->get();

            $parentCategories = Category::whereIn('id', function($query) use ($user) {
                    $query->select('category_id')
                          ->from('user_categories')
                          ->where('user_id', $user->id)
                          ->where('is_active', true);
                })
                ->whereNull('parent_id')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'categories' => $categories,
                    'parentCategories' => $parentCategories,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch categories',
                'data' => [
                    'categories' => [],
                    'parentCategories' => [],
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['user_id'] = auth()->id();

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    public function show(Category $category)
    {
        $this->authorize('view', $category);

        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'category' => $category,
        ]);
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}