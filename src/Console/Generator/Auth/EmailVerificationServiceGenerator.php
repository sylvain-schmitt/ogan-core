<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * ✉️ EMAIL VERIFICATION SERVICE GENERATOR
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Console\Generator\Auth;

use Ogan\Console\Generator\AbstractGenerator;

class EmailVerificationServiceGenerator extends AbstractGenerator
{
    public function generate(string $projectRoot, bool $force = false): array
    {
        $generated = [];
        $skipped = [];

        $path = $projectRoot . '/src/Security/EmailVerificationService.php';
        $this->ensureDirectory(dirname($path));

        if (!$this->fileExists($path) || $force) {
            $this->writeFile($path, $this->getTemplate());
            $generated[] = 'src/Security/EmailVerificationService.php';
        } else {
            $skipped[] = 'src/Security/EmailVerificationService.php (existe déjà)';
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private function getTemplate(): string
    {
        return <<<'PHP'
<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * ✉️ EMAIL VERIFICATION SERVICE
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Gère la vérification d'email des utilisateurs.
 * 
 * Le template de l'email est modifiable dans :
 * templates/emails/verify-email.ogan
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace App\Security;

use App\Model\User;
use Ogan\Config\Config;
use Ogan\Mail\Mailer;
use Ogan\Mail\Email;
use Ogan\View\View;

class EmailVerificationService
{
    /**
     * Envoie un email de vérification
     */
    public function sendVerification(User $user): bool
    {
        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationToken($token);
        $user->save();

        try {
            $mailer = new Mailer(Config::get('mailer.dsn', 'smtp://localhost:1025'));
            
            $verifyUrl = $this->getBaseUrl() . '/verify-email/' . $token;
            
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
            $htmlContent = View::render('emails/verify_email.ogan', [
                'user' => $user,
                'url' => $verifyUrl,
                'appName' => Config::get('app.name', 'Mon Application'),
            ]);
            
            $email = (new Email())
                ->from((string) $fromEmail, (string) $fromName)
                ->to($user->getEmail())
                ->subject('Vérifiez votre adresse email')
                ->html($htmlContent);
            
            $mailer->send($email);
            return true;
        } catch (\Exception $e) {
            // Log error but don't break registration
            return false;
        }
    }

    /**
     * Vérifie un token de vérification
     */
    public function verify(string $token): ?User
    {
        $result = User::where('email_verification_token', '=', $token)->first();
        
        if (!$result) {
            return null;
        }

        // Récupérer l'utilisateur via find() pour une hydratation correcte
        $userId = is_array($result) ? ($result['id'] ?? null) : ($result->id ?? null);
        $user = User::find($userId);
        if (!$user) {
            return null;
        }
        
        // Marquer comme vérifié
        $user->setEmailVerifiedAt(date('Y-m-d H:i:s'));
        $user->setEmailVerificationToken(null);
        $user->save();
        
        return $user;
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
