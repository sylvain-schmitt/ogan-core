<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📝 AUTH FORM TYPE GENERATOR
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Génère tous les FormTypes pour l'authentification.
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Console\Generator\Auth;

use Ogan\Console\Generator\AbstractGenerator;

class AuthFormTypeGenerator extends AbstractGenerator
{
    public function generate(string $projectRoot, bool $force = false): array
    {
        $generated = [];
        $skipped = [];

        $formsDir = $projectRoot . '/src/Form';
        $this->ensureDirectory($formsDir);

        $forms = [
            'LoginFormType.php' => 'getLoginFormTemplate',
            'RegisterFormType.php' => 'getRegisterFormTemplate',
            'ForgotPasswordFormType.php' => 'getForgotPasswordFormTemplate',
            'ResetPasswordFormType.php' => 'getResetPasswordFormTemplate',
            'ProfileFormType.php' => 'getProfileFormTemplate',
        ];

        foreach ($forms as $filename => $method) {
            $path = $formsDir . '/' . $filename;
            if (!$this->fileExists($path) || $force) {
                $this->writeFile($path, $this->$method());
                $generated[] = "src/Form/{$filename}";
            } else {
                $skipped[] = "src/Form/{$filename} (existe déjà)";
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private function getLoginFormTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Form;

use Ogan\Form\AbstractType;
use Ogan\Form\FormBuilder;
use Ogan\Form\Types\EmailType;
use Ogan\Form\Types\PasswordType;
use Ogan\Form\Types\CheckboxType;
use Ogan\Form\Types\SubmitType;
use Ogan\Form\Constraint\Required;
use Ogan\Form\Constraint\Email;

class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new Required('L\'email est requis.'),
                    new Email('Veuillez entrer une adresse email valide.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'votre@email.com',
                    'autofocus' => true
                ]
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'constraints' => [
                    new Required('Le mot de passe est requis.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'Votre mot de passe'
                ]
            ])
            ->add('remember_me', CheckboxType::class, [
                'label' => 'Se souvenir de moi',
                'constraints' => [],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Se connecter',
                'attr' => ['class' => 'w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors']
            ]);
    }
}
PHP;
    }

    private function getRegisterFormTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Form;

use App\Model\User;
use Ogan\Form\AbstractType;
use Ogan\Form\FormBuilder;
use Ogan\Form\Types\TextType;
use Ogan\Form\Types\EmailType;
use Ogan\Form\Types\PasswordType;
use Ogan\Form\Types\SubmitType;
use Ogan\Form\Constraint\Required;
use Ogan\Form\Constraint\MinLength;
use Ogan\Form\Constraint\MaxLength;
use Ogan\Form\Constraint\Email;
use Ogan\Form\Constraint\EqualTo;
use Ogan\Form\Constraint\UniqueEntity;

class RegisterFormType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom complet',
                'constraints' => [
                    new Required('Le nom est requis.'),
                    new MinLength(2, 'Le nom doit contenir au moins 2 caractères.'),
                    new MaxLength(100, 'Le nom ne doit pas dépasser 100 caractères.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'Votre nom complet',
                    'autofocus' => true
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new Required('L\'email est requis.'),
                    new Email('Veuillez entrer une adresse email valide.'),
                    new UniqueEntity(User::class, 'email', 'Cet email est déjà utilisé.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'votre@email.com'
                ]
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'constraints' => [
                    new Required('Le mot de passe est requis.'),
                    new MinLength(8, 'Le mot de passe doit contenir au moins 8 caractères.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'Minimum 8 caractères'
                ]
            ])
            ->add('password_confirm', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'constraints' => [
                    new Required('Veuillez confirmer votre mot de passe.'),
                    new EqualTo('password', 'Les mots de passe ne correspondent pas.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'Retapez votre mot de passe'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Créer un compte',
                'attr' => ['class' => 'w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors']
            ]);
    }
}
PHP;
    }

    private function getForgotPasswordFormTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Form;

use Ogan\Form\AbstractType;
use Ogan\Form\FormBuilder;
use Ogan\Form\Types\EmailType;
use Ogan\Form\Types\PasswordType;
use Ogan\Form\Types\SubmitType;
use Ogan\Form\Constraint\Required;
use Ogan\Form\Constraint\Email as EmailConstraint;
use Ogan\Form\Constraint\MinLength;
use Ogan\Form\Constraint\EqualTo;

class ForgotPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        $sendEmail = $options['send_email'] ?? false;

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Votre adresse email',
                'required' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'votre@email.com',
                    'autofocus' => true
                ],
                'constraints' => [
                    new Required('L\'email est requis.'),
                    new EmailConstraint('Veuillez entrer un email valide.')
                ]
            ]);

        // If not sending email, add password fields for direct reset
        if (!$sendEmail) {
            $builder
                ->add('new_password', PasswordType::class, [
                    'label' => 'Nouveau mot de passe',
                    'required' => true,
                    'attr' => [
                        'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                        'placeholder' => 'Minimum 8 caractères'
                    ],
                    'constraints' => [
                        new Required('Le mot de passe est requis.'),
                        new MinLength(8, 'Le mot de passe doit contenir au moins 8 caractères.')
                    ]
                ])
                ->add('new_password_confirm', PasswordType::class, [
                    'label' => 'Confirmer le mot de passe',
                    'required' => true,
                    'attr' => [
                        'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                        'placeholder' => 'Retapez votre mot de passe'
                    ],
                    'constraints' => [
                        new Required('La confirmation est requise.'),
                        new EqualTo('new_password', 'Les mots de passe ne correspondent pas.')
                    ]
                ]);
        }

        $buttonLabel = $sendEmail 
            ? 'Envoyer le lien de réinitialisation' 
            : 'Réinitialiser mon mot de passe';

        $builder->add('submit', SubmitType::class, [
            'label' => $buttonLabel,
            'attr' => ['class' => 'w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors']
        ]);
    }
}
PHP;
    }

    private function getResetPasswordFormTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Form;

use Ogan\Form\AbstractType;
use Ogan\Form\FormBuilder;
use Ogan\Form\Types\PasswordType;
use Ogan\Form\Types\SubmitType;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'required' => true,
                'min' => 8,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'Minimum 8 caractères',
                    'autofocus' => true
                ]
            ])
            ->add('password_confirm', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'required' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                    'placeholder' => 'Retapez votre mot de passe'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Réinitialiser mon mot de passe',
                'attr' => ['class' => 'w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors']
            ]);
    }
}
PHP;
    }

    private function getProfileFormTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Form;

use App\Model\User;
use Ogan\Form\AbstractType;
use Ogan\Form\FormBuilder;
use Ogan\Form\Types\TextType;
use Ogan\Form\Types\EmailType;
use Ogan\Form\Types\PasswordType;
use Ogan\Form\Types\SubmitType;
use Ogan\Form\Constraint\Required;
use Ogan\Form\Constraint\Email;
use Ogan\Form\Constraint\MinLength;
use Ogan\Form\Constraint\UniqueEntity;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        // Récupérer l'ID utilisateur pour exclure de la vérification d'unicité
        $userId = $options['user_id'] ?? null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom complet',
                'constraints' => [
                    new Required('Le nom est obligatoire'),
                    new MinLength(2, 'Le nom doit contenir au moins 2 caractères')
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white',
                    'placeholder' => 'Votre nom'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new Required('L\'email est obligatoire'),
                    new Email('L\'email n\'est pas valide'),
                    new UniqueEntity(User::class, 'email', 'Cet email est déjà utilisé.', $userId)
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white',
                    'placeholder' => 'votre@email.com'
                ]
            ])
            ->add('current_password', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'required' => false,
                'constraints' => [],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white',
                    'placeholder' => 'Requis pour changer le mot de passe'
                ]
            ])
            ->add('new_password', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'required' => false,
                'constraints' => [],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white',
                    'placeholder' => 'Laisser vide pour ne pas changer'
                ]
            ])
            ->add('confirm_password', PasswordType::class, [
                'label' => 'Confirmer le nouveau mot de passe',
                'required' => false,
                'constraints' => [],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white',
                    'placeholder' => 'Confirmer le nouveau mot de passe'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer les modifications',
                'attr' => ['class' => 'w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors']
            ]);
    }
}
PHP;
    }
}
