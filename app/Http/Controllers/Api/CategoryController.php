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

    /**
     * Get all available categories for selection
     */
    public function getAllCategories()
    {
        try {
            $parentCategories = Category::whereNull('parent_id')
                ->with(['children' => function($query) {
                    $query->orderBy('name');
                }])
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->groupBy('type');

            return response()->json([
                'status' => true,
                'data' => [
                    'categories' => $parentCategories,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch all categories',
                'data' => [
                    'categories' => [],
                ]
            ], 500);
        }
    }

    /**
     * Store user's category selections
     */
    public function storeUserSelection(Request $request)
    {
        $request->validate([
            'categories' => 'required|array|min:1',
            'categories.*' => 'integer|exists:categories,id'
        ]);

        try {
            $user = auth()->user();

            // Remove any existing selections
            $user->userCategories()->delete();

            // Add new selections
            foreach ($request->categories as $categoryId) {
                $user->userCategories()->create([
                    'category_id' => $categoryId,
                    'is_active' => true
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Categories saved successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to save category selections'
            ], 500);
        }
    }

    /**
     * Check if user needs to select categories
     */
    public function checkSelection()
    {
        try {
            $user = auth()->user();
            $needsSelection = !$user->userCategories()->where('is_active', true)->exists();

            return response()->json([
                'status' => true,
                'needs_selection' => $needsSelection
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to check selection status'
            ], 500);
        }
    }
}
