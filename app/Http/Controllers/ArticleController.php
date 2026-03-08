<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;

        $query = Article::with('category')
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('description');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($cat = $request->input('category_id')) {
            $query->where('category_id', $cat);
        }

        $articles   = $query->paginate(20)->withQueryString();
        $categories = Category::where('company_id', $companyId)->orderBy('name')->get();

        return view('articles.index', compact('articles', 'categories'));
    }

    public function create()
    {
        $categories = Category::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        return view('articles.form', ['article' => new Article(), 'categories' => $categories]);
    }

    public function store(Request $request)
    {
        $data = $this->validateArticle($request);
        $data['company_id'] = auth()->user()->company_id;

        Article::create($data);

        return redirect()->route('articles.index')
            ->with('success', 'Artículo creado correctamente.');
    }

    public function edit(Article $article)
    {
        $this->authorizeCompany($article);
        $categories = Category::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        return view('articles.form', compact('article', 'categories'));
    }

    public function update(Request $request, Article $article)
    {
        $this->authorizeCompany($article);
        $article->update($this->validateArticle($request, $article->id));

        return redirect()->route('articles.index')
            ->with('success', 'Artículo actualizado correctamente.');
    }

    public function destroy(Article $article)
    {
        $this->authorizeCompany($article);
        $article->update(['active' => false]);

        return redirect()->route('articles.index')
            ->with('success', 'Artículo dado de baja.');
    }

    private function validateArticle(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'code'        => [
                'required', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('articles')->where('company_id', $companyId)->ignore($ignoreId),
            ],
            'description' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'cost_price'  => 'required|numeric|min:0',
            'sale_price'  => 'required|numeric|min:0',
        ]);
    }

    private function authorizeCompany(Article $article): void
    {
        abort_unless($article->company_id === auth()->user()->company_id, 403);
    }
}
