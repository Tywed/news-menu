<?php

namespace Tywed\Webtrees\Module\NewsMenu\Controllers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CommentRepository;
use Tywed\Webtrees\Module\NewsMenu\Services\NewsService;
use Fisharebest\Webtrees\View;
use Tywed\Webtrees\Module\NewsMenu\NewsMenu;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Registry;

class CommentController
{
    use ViewResponseTrait;
    
    private CommentRepository $commentRepository;
    private NewsService $newsService;
    private NewsMenu $module;
    private UserService $userService;

    /**
     * Constructor
     *
     * @param CommentRepository $commentRepository
     * @param NewsService $newsService
     * @param NewsMenu $module
     */
    public function __construct(
        CommentRepository $commentRepository,
        NewsService $newsService,
        NewsMenu $module
    ) {
        $this->commentRepository = $commentRepository;
        $this->newsService = $newsService;
        $this->module = $module;
        $this->userService = new UserService();
    }

    /**
     * Create a new comment
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user_id = Auth::id();
        
        // Guest users cannot comment or users without permission
        if ($user_id === null || !$this->module->canAddComments($tree)) {
            throw new HttpAccessDeniedException(I18N::translate('You do not have permission to add comments.'));
        }
        
        $news_id = Validator::queryParams($request)->integer('news_id');
        $comment = Validator::parsedBody($request)->string('comment');
        
        // Don't allow empty comments
        if (trim($comment) === '') {
            $message = I18N::translate('Comment cannot be empty');
            FlashMessages::addMessage($message, 'danger');
        } else {
            $this->commentRepository->create($news_id, $user_id, $comment);
            $message = I18N::translate('Comment added');
            FlashMessages::addMessage($message, 'success');
        }

        $url = route('module', [
            'tree' => $tree->name(),
            'module' => $this->module->name(),
            'action' => 'ShowNews',
            'news_id' => $news_id,
        ]);

        return redirect($url);
    }

    /**
     * Delete a comment
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id');
        $comments_id = Validator::queryParams($request)->integer('comments_id');

        if (!$this->module->canEditNews($tree)) {
            throw new HttpAccessDeniedException();
        }

        $comment = $this->commentRepository->find($comments_id);
        
        if ($comment === null) {
            $message = I18N::translate('Comment not found');
            FlashMessages::addMessage($message, 'danger');
        } else {
            $this->commentRepository->delete($comment);
            $message = I18N::translate('Comment deleted');
            FlashMessages::addMessage($message, 'success');
        }

        $url = route('module', [
            'tree' => $tree->name(),
            'module' => $this->module->name(),
            'action' => 'ShowNews',
            'news_id' => $news_id,
        ]);

        return redirect($url);
    }

    /**
     * Like a comment
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function like(ServerRequestInterface $request): ResponseInterface
    {
        $comments_id = Validator::queryParams($request)->integer('comments_id');
        $user_id = Auth::id();
        
        if ($user_id === null) {
            return response([
                'success' => false,
                'message' => I18N::translate('You must be logged in to like comments'),
            ]);
        }

        $this->commentRepository->addLike($comments_id, $user_id);
        $likes_count = $this->commentRepository->getLikesCount($comments_id);

        return response([
            'success' => true,
            'data' => [
                'likes_count' => $likes_count,
            ],
        ]);
    }
} 