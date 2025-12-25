<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🔑 PASSWORD RESET SERVICE GENERATOR
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Console\Generator\Auth;

use Ogan\Console\Generator\AbstractGenerator;

class PasswordResetServiceGenerator extends AbstractGenerator
{
    public function generate(string $projectRoot, bool $force = false): array
    {
        $generated = [];
        $skipped = [];

        $path = $projectRoot . '/src/Security/PasswordResetService.php';
        $this->ensureDirectory(dirname($path));

        if (!$this->fileExists($path) || $force) {
            $this->writeFile($path, $this->getTemplate());
            $generated[] = 'src/Security/PasswordResetService.php';
        } else {
            $skipped[] = 'src/Security/PasswordResetService.php (existe déjà)';
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private function getTemplate(): string
    {
        return <<<'PHP'
<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🔑 PASSWORD RESET SERVICE
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Gère la réinitialisation des mots de passe (via email ou direct).
 * 
 * Le template de l'email est modifiable dans :
 * templates/emails/reset-password.ogan
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace App\Security;

use App\Model\User;
use Ogan\Config\Config;
use Ogan\Mail\Mailer;
use Ogan\Mail\Email;
use Ogan\Security\PasswordHasher;
use Ogan\View\View;

class PasswordResetService
{
    private PasswordHasher $hasher;

    public function __construct()
    {
        $this->hasher = new PasswordHasher();
    }

    /**
     * Envoie un email de réinitialisation
     */
    public function sendResetEmail(User $user): bool
    {
        $token = bin2hex(random_bytes(32));
        $user->setPasswordResetToken($token);
        $user->setPasswordResetExpiresAt(date('Y-m-d H:i:s', strtotime('+1 hour')));
        $user->save();

        try {
            $mailer = new Mailer(Config::get('mailer.dsn', 'smtp://localhost:1025'));
            
            $resetUrl = $this->getBaseUrl() . '/reset-password/' . $token;
            
            // S'assurer que mail.from est une string
            $fromEmail = Config::get('mail.from', 'noreply@example.com');
            if (is_array($fromEmail)) {
                $fromEmail = $fromEmail[0] ?? 'noreply@example.com';
            }
            $fromName = Config::get('mail.from_name', '');
            if (is_array($fromName)) {
                $fromName = $fromName[0] ?? '';
            }
            
            // Rendre le template email (modifiable par l'utilisateur)
            $htmlContent = View::render('emails/password_reset.ogan', [
                'user' => $user,
                'url' => $resetUrl,
                'appName' => Config::get('app.name', 'Mon Application'),
            ]);
            
            $email = (new Email())
                ->from((string) $fromEmail, (string) $fromName)
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe')
                ->html($htmlContent);
            
            $mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Valide un token de réinitialisation
     */
    public function validateToken(string $token): ?User
    {
        $result = User::where('password_reset_token', '=', $token)->first();
        
        if (!$result) {
            return null;
        }

        // Récupérer l'utilisateur via find() pour une hydratation correcte
        $userId = is_array($result) ? ($result['id'] ?? null) : ($result->id ?? null);
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        // Vérifier l'expiration
        if ($user->getPasswordResetExpiresAt() < date('Y-m-d H:i:s')) {
            return null;
        }
        
        return $user;
    }

    /**
     * Réinitialise le mot de passe avec un token
     */
    public function resetPassword(User $user, string $newPassword): bool
    {
        $user->setPassword($this->hasher->hash($newPassword));
        $user->setPasswordResetToken(null);
        $user->setPasswordResetExpiresAt(null);
        return $user->save();
    }

    /**
     * Réinitialisation directe (sans email)
     */
    public function resetPasswordDirect(string $email, string $newPassword): bool
    {
        $user = User::findByEmail($email);
        
        if (!$user) {
            return false;
        }

        $user->setPassword($this->hasher->hash($newPassword));
        return $user->save();
    }

    /**
     * Récupère l'URL de base
     */
    private function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
PHP;
    }
}
