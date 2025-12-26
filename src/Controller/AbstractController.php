<?php

namespace Ogan\Controller;

use Ogan\View\View;
use Ogan\Http\Request;
use Ogan\Http\Response;

abstract class AbstractController
{
    protected Request $request;
    protected Response $response;
    protected View $view;
    protected string $layout;

    protected array $config;
    protected ?\Ogan\Form\FormFactory $formFactory = null;
    protected ?\Ogan\Session\SessionInterface $session = null;
    protected ?\Ogan\DependencyInjection\ContainerInterface $container = null;

    /**
     * Appelé automatiquement par le Router lors du dispatch.
     */
    /**
     * Appelé automatiquement par le Router lors du dispatch.
     */
    public function setRequestResponse(Request $request, Response $response, \Ogan\DependencyInjection\ContainerInterface $container): void
    {
        $this->request = $request;
        $this->response = $response;

        // On charge toute la configuration depuis Config (qui gère .env)
        $this->config = \Ogan\Config\Config::all();

        // Initialisation du moteur de vue
        $useCompiler = $this->config['view']['use_compiler'] ?? false;
        $cacheDir = $this->config['view']['cache_dir'] ?? null;
        $this->view = new View($this->config['view']['templates_path'], $useCompiler, $cacheDir);
        $this->layout = $this->config['view']['default_layout'];

        // Injection du CsrfManager dans la vue si disponible
        if ($container->has(\Ogan\Security\CsrfManager::class)) {
            $this->view->setCsrfManager($container->get(\Ogan\Security\CsrfManager::class));
        }

        // Injection du FormFactory si disponible
        if ($container->has(\Ogan\Form\FormFactory::class)) {
            $this->formFactory = $container->get(\Ogan\Form\FormFactory::class);
        } else {
            // Créer un FormFactory avec le Validator du container
            $validator = $container->has(\Ogan\Validation\Validator::class)
                ? $container->get(\Ogan\Validation\Validator::class)
                : null;
            $this->formFactory = new \Ogan\Form\FormFactory($validator);
        }

        // Injection de la session dans le contrôleur et la vue
        if ($request->hasSession()) {
            $this->session = $request->getSession();
            $this->view->setSession($this->session);
        }

        // Injection du Router dans la vue (pour les helpers route() et url())
        if ($container->has(\Ogan\Router\Router::class)) {
            $this->view->setRouter($container->get(\Ogan\Router\Router::class));
        }

        // Stocker le container pour accès aux services
        $this->container = $container;
    }

    /**
     * Réponse JSON simple.
     * 
     * @param mixed $data Données à encoder
     * @param int $status Code HTTP
     * @return Response
     */
    protected function json($data, int $status = 200): Response
    {
        // Si c'est un modèle, le convertir en array
        if (is_object($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }

        // Si c'est une collection de modèles
        if (is_array($data) && isset($data[0]) && is_object($data[0]) && method_exists($data[0], 'toArray')) {
            $data = array_map(fn($item) => $item->toArray(), $data);
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = new Response($json, $status);
        $response->setHeader('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Retourne du texte brut (debug ou simple output).
     */
    protected function renderText(string $text): void
    {
        $this->response->send($text);
    }

    /**
     * Redirection HTTP.
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return $this->response->redirect($url, $status);
    }

    /**
     * Ajoute un message flash à la session.
     * 
     * Les messages flash sont des messages temporaires stockés en session,
     * affichés une fois à l'utilisateur puis supprimés automatiquement.
     * 
     * @param string $type Type de message (success, error, warning, info)
     * @param string $message Le message à afficher
     */
    protected function addFlash(string $type, string $message): void
    {
        if ($this->session === null) {
            throw new \RuntimeException('Cannot add flash message: no session available.');
        }

        $this->session->setFlash($type, $message);
    }

    /**
     * Rendu d’un partial ou d’un component réutilisable.
     */
    protected function renderPartial(string $template, array $params = []): string
    {
        return $this->view->render($template, $params);
    }

    /**
     * Rendu complet d’une page avec layout + bloc "body".
     */
    protected function render(string $template, array $params = []): Response
    {
        // Injecter l'utilisateur courant dans la vue (pour app.user)
        try {
            $user = $this->getUser();
            if ($user) {
                $this->view->setUser($user);
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs si la session ou la DB n'est pas prête
        }

        // Gestion du titre (celui du contrôleur > celui de config)
        $params['title'] = $params['title']
            ?? $this->config['view']['default_title'];

        // Avec le moteur de template avancé, la vue gère elle-même son layout via extend()
        $content = $this->view->render($template, $params);
        return $this->response->setContent($content);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 🔐 MÉTHODES DE SÉCURITÉ / AUTORISATION
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Récupère l'utilisateur actuellement connecté
     */
    protected function getUser(): ?\Ogan\Security\UserInterface
    {
        if (!$this->session) {
            return null;
        }

        $userId = $this->session->get('_auth_user_id');
        if (!$userId) {
            return null;
        }

        // Chercher l'utilisateur via la classe configurée
        $userClass = \Ogan\Config\Config::get('auth.user_class', 'App\\Model\\User');
        if (class_exists($userClass) && method_exists($userClass, 'find')) {
            return $userClass::find($userId);
        }

        return null;
    }

    /**
     * Vérifie si l'utilisateur courant a une permission
     * 
     * @param string $attribute L'attribut à vérifier (ex: 'ROLE_ADMIN', 'edit')
     * @param mixed $subject Le sujet optionnel (ex: instance Post)
     * @return bool true si autorisé
     */
    protected function isGranted(string $attribute, mixed $subject = null): bool
    {
        $user = $this->getUser();
        $checker = new \Ogan\Security\Authorization\AuthorizationChecker($user);
        return $checker->isGranted($attribute, $subject);
    }

    /**
     * Refuse l'accès si l'utilisateur n'a pas la permission
     * 
     * @param string $attribute L'attribut à vérifier
     * @param mixed $subject Le sujet optionnel
     * @param string $message Message d'erreur
     * @throws \Ogan\Security\Authorization\AccessDeniedException
     */
    protected function denyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->isGranted($attribute, $subject)) {
            throw new \Ogan\Security\Authorization\AccessDeniedException($message);
        }
    }

    /**
     * Crée une réponse "Access Denied" (403)
     * 
     * @param string $message Message à afficher
     * @param int $status Code HTTP (403 par défaut)
     */
    protected function accessDenied(string $message = 'Access Denied.', int $status = 403): Response
    {
        return $this->response
            ->setStatusCode($status)
            ->setContent($this->view->render('errors/403.ogan', ['message' => $message]));
    }

    /**
     * Bloque l'accès si une condition est vraie
     * 
     * Utile pour désactiver des routes via configuration (.env).
     * 
     * Exemple:
     *   $this->denyAccessIf(!Config::get('registration.enabled', true), 'Inscriptions fermées');
     * 
     * @param bool $condition Si true, l'accès est refusé
     * @param string $message Message à afficher
     * @throws \Ogan\Security\Authorization\AccessDeniedException
     */
    protected function denyAccessIf(bool $condition, string $message = 'Cette fonctionnalité est désactivée.'): void
    {
        if ($condition) {
            throw new \Ogan\Security\Authorization\AccessDeniedException($message);
        }
    }

    /**
     * Bloque l'accès si une fonctionnalité est désactivée dans la config
     * 
     * Exemple:
     *   $this->denyIfDisabled('registration', 'Les inscriptions sont fermées.');
     * 
     * @param string $feature Nom de la fonctionnalité (ex: 'registration', 'contact')
     * @param string $message Message à afficher
     * @throws \Ogan\Security\Authorization\AccessDeniedException
     */
    protected function denyIfDisabled(string $feature, ?string $message = null): void
    {
        $enabled = \Ogan\Config\Config::get("{$feature}.enabled", true);
        if (!$enabled) {
            $message = $message ?? "Cette fonctionnalité ({$feature}) est désactivée.";
            throw new \Ogan\Security\Authorization\AccessDeniedException($message);
        }
    }
}
