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
use Tywed\Webtrees\Module\NewsMenu\Helpers\AppHelper;

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
     * @param HtmlService|null $htmlService
     */
    public function __construct(
        CategoryRepository $categoryRepository,
        NewsMenu $module,
        ?HtmlService $htmlService = null
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->module = $module;
        $this->htmlService = $htmlService ?? AppHelper::get(HtmlService::class);
    }

    /**
     * Get list of available languages from module
     * Delegates to NewsMenu::getAvailableLanguages() which uses I18N::activeLocales()
     *
     * @return array<string> Language codes
     */
    private function getAvailableLanguages(): array
    {
        return $this->module->getAvailableLanguages();
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

        $name = $params['name'] ?? ''; // Fallback name
        $description = $params['description'] ?? null;
        $sortOrder = (int)($params['sort_order'] ?? 0);
        $translations = $params['translations'] ?? []; // ['en' => 'Name', 'de' => 'Name']

        if ($name === '') {
            $message = I18N::translate('Category name cannot be empty');
            FlashMessages::addMessage($message, 'danger');
        } else {
            $name = $this->htmlService->sanitize($name);
            if ($description !== null) {
                $description = $this->htmlService->sanitize($description);
            }

            // Create category
            $category = $this->categoryRepository->create($name, $description, $sortOrder);

            // Save translations
            foreach ($translations as $language => $translatedName) {
                if (!empty($translatedName)) {
                    $translatedName = $this->htmlService->sanitize($translatedName);
                    $this->categoryRepository->saveTranslation(
                        $category->getCategoryId(),
                        $language,
                        $translatedName
                    );
                }
            }

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
            'available_languages' => $this->getAvailableLanguages(),
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
        $name = $params['name'] ?? ''; // Fallback name
        $description = $params['description'] ?? null;
        $sortOrder = (int)($params['sort_order'] ?? 0);
        $translations = $params['translations'] ?? []; // ['en' => 'Name', 'de' => 'Name']

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

                // Update category
                $this->categoryRepository->update($category, $name, $description, $sortOrder);

                // Update translations
                foreach ($translations as $language => $translatedName) {
                    if (!empty($translatedName)) {
                        $translatedName = $this->htmlService->sanitize($translatedName);
                        $this->categoryRepository->saveTranslation(
                            $category_id,
                            $language,
                            $translatedName
                        );
                    } else {
                        // Remove translation if empty
                        $this->categoryRepository->deleteTranslation($category_id, $language);
                    }
                }

                $message = I18N::translate('Category updated successfully');
                FlashMessages::addMessage($message, 'success');
            }
        }

        return redirect($this->module->getConfigLink());
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
