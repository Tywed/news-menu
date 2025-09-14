<?php

namespace Tywed\Webtrees\Module\NewsMenu\Controllers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Services\HtmlService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CategoryRepository;
use Tywed\Webtrees\Module\NewsMenu\NewsMenu;
use Fisharebest\Webtrees\Http\ViewResponseTrait;

/**
 * Controller for category management
 */
class CategoryController
{
    use ViewResponseTrait;
    
    private CategoryRepository $categoryRepository;
    private NewsMenu $module;
    private HtmlService $htmlService;

    /**
     * Constructor
     * 
     * @param CategoryRepository $categoryRepository
     * @param NewsMenu $module
     */
    public function __construct(
        CategoryRepository $categoryRepository,
        NewsMenu $module
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->module = $module;
        $this->htmlService = new HtmlService();
    }

    /**
     * Add a new category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function add(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }
        
        $params = (array) $request->getParsedBody();
        
        $name = $params['name'] ?? '';
        $description = $params['description'] ?? null;
        $sortOrder = (int)($params['sort_order'] ?? 0);
        
        if ($name === '') {
            $message = I18N::translate('Category name cannot be empty');
            FlashMessages::addMessage($message, 'danger');
        } else {
            $name = $this->htmlService->sanitize($name);
            if ($description !== null) {
                $description = $this->htmlService->sanitize($description);
            }
            
            $this->categoryRepository->create($name, $description, $sortOrder);
            
            $message = I18N::translate('Category added successfully');
            FlashMessages::addMessage($message, 'success');
        }
        
        return redirect($this->module->getConfigLink());
    }

    /**
     * Show the edit category form
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }
        
        $category_id = Validator::queryParams($request)->integer('category_id', 0);
        
        if ($category_id === 0) {
            $message = I18N::translate('Invalid category ID');
            FlashMessages::addMessage($message, 'danger');
            return redirect($this->module->getConfigLink());
        }
        
        $category = $this->categoryRepository->find($category_id);
        
        if ($category === null) {
            $message = I18N::translate('Category not found');
            FlashMessages::addMessage($message, 'danger');
            return redirect($this->module->getConfigLink());
        }
        
        $this->layout = 'layouts/administration';
        
        return $this->viewResponse($this->module->name() . '::edit-category', [
            'title' => I18N::translate('Edit Category'),
            'category' => $category,
            'module_name' => $this->module->name(),
        ]);
    }
    
    /**
     * Update a category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }
        
        $params = (array) $request->getParsedBody();
        
        $category_id = (int)($params['category_id'] ?? 0);
        $name = $params['name'] ?? '';
        $description = $params['description'] ?? null;
        $sortOrder = (int)($params['sort_order'] ?? 0);
        
        if ($category_id === 0) {
            $message = I18N::translate('Invalid category ID');
            FlashMessages::addMessage($message, 'danger');
        } elseif ($name === '') {
            $message = I18N::translate('Category name cannot be empty');
            FlashMessages::addMessage($message, 'danger');
        } else {
            $category = $this->categoryRepository->find($category_id);
            
            if ($category === null) {
                $message = I18N::translate('Category not found');
                FlashMessages::addMessage($message, 'danger');
            } else {
                $name = $this->htmlService->sanitize($name);
                if ($description !== null) {
                    $description = $this->htmlService->sanitize($description);
                }
                
                $this->categoryRepository->update($category, $name, $description, $sortOrder);
                
                $message = I18N::translate('Category updated successfully');
                FlashMessages::addMessage($message, 'success');
            }
        }
        
        return redirect($this->module->getConfigLink());
    }

    /**
     * Upsert category translation
     */
    public function postUpsertCategoryTranslationAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $params = (array) $request->getParsedBody();
        $categoryId = (int)($params['category_id'] ?? 0);
        $language = (string)($params['language'] ?? '');
        $name = (string)($params['name'] ?? '');

        if ($categoryId === 0 || $language === '' || trim($name) === '') {
            return response(['success' => false, 'message' => I18N::translate('Invalid data')]);
        }

        $this->categoryRepository->upsertTranslation($categoryId, $language, $this->htmlService->sanitize($name));

        return response(['success' => true]);
    }

    /**
     * Delete category translation
     */
    public function postDeleteCategoryTranslationAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $params = (array) $request->getParsedBody();
        $categoryId = (int)($params['category_id'] ?? 0);
        $language = (string)($params['language'] ?? '');

        if ($categoryId === 0 || $language === '') {
            return response(['success' => false, 'message' => I18N::translate('Invalid data')]);
        }

        $this->categoryRepository->deleteTranslation($categoryId, $language);

        return response(['success' => true]);
    }

    /**
     * Delete a category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }
        
        $params = (array) $request->getParsedBody();
        
        $categoryId = (int)($params['category_id'] ?? 0);
        
        if ($categoryId === 0) {
            $message = I18N::translate('Invalid category ID');
            FlashMessages::addMessage($message, 'danger');
        } else {
            $category = $this->categoryRepository->find($categoryId);
            
            if ($category === null) {
                $message = I18N::translate('Category not found');
                FlashMessages::addMessage($message, 'danger');
            } else {
                $this->categoryRepository->delete($category);
                
                $message = I18N::translate('Category deleted successfully');
                FlashMessages::addMessage($message, 'success');
            }
        }
        
        return redirect($this->module->getConfigLink());
    }
} 