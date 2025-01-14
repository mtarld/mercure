<?php

/*
 * This file is part of the Mercure Component project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Mercure\Jwt;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token\RegisteredClaims;
use Symfony\Component\Mercure\Exception\InvalidArgumentException;

final class LcobucciFactory implements TokenFactoryInterface
{
    /**
     * @var array<string, class-string<Signer>>
     */
    public const SIGN_ALGORITHMS = [
        'hmac.sha256' => Signer\Hmac\Sha256::class,
        'hmac.sha384' => Signer\Hmac\Sha384::class,
        'hmac.sha512' => Signer\Hmac\Sha512::class,
        'ecdsa.sha256' => Signer\Ecdsa\Sha256::class,
        'ecdsa.sha384' => Signer\Ecdsa\Sha384::class,
        'ecdsa.sha512' => Signer\Ecdsa\Sha512::class,
        'rsa.sha256' => Signer\Rsa\Sha256::class,
        'rsa.sha384' => Signer\Rsa\Sha384::class,
        'rsa.sha512' => Signer\Rsa\Sha512::class,
    ];

    private $configurations;

    public function __construct(string $secret, string $algorithm = 'hmac.sha256')
    {
        if (!class_exists(Key\InMemory::class)) {
            throw new \LogicException('You cannot use "Symfony\Component\Mercure\Token\LcobucciFactory" as the "lcobucci/jwt" package is not installed. Try running "composer require lcobucci/jwt".');
        }

        if (!\array_key_exists($algorithm, self::SIGN_ALGORITHMS)) {
            throw InvalidArgumentException::forInvalidAlgorithm($algorithm, array_keys(self::SIGN_ALGORITHMS));
        }

        $signerClass = self::SIGN_ALGORITHMS[$algorithm];
        $this->configurations = Configuration::forSymmetricSigner(
            new $signerClass(),
            Key\InMemory::plainText($secret)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $subscribe = [], array $publish = [], array $additionalClaims = []): string
    {
        $builder = $this->configurations->builder();

        $additionalClaims['mercure'] = [
            'publish' => $publish,
            'subscribe' => $subscribe,
        ];

        foreach ($additionalClaims as $name => $value) {
            switch ($name) {
                case RegisteredClaims::AUDIENCE:
                    $builder = $builder->permittedFor(...(array) $value);
                    break;
                case RegisteredClaims::EXPIRATION_TIME:
                    $builder = $builder->expiresAt($value);
                    break;
                case RegisteredClaims::ISSUED_AT:
                    $builder = $builder->issuedAt($value);
                    break;
                case RegisteredClaims::ISSUER:
                    $builder = $builder->issuedBy($value);
                    break;
                case RegisteredClaims::SUBJECT:
                    $builder = $builder->relatedTo($value);
                    break;
                case RegisteredClaims::ID:
                    $builder = $builder->identifiedBy($value);
                    break;
                case RegisteredClaims::NOT_BEFORE:
                    $builder = $builder->canOnlyBeUsedAfter($value);
                    break;
                default:
                    $builder = $builder->withClaim($name, $value);
            }
        }

        return $builder
            ->getToken($this->configurations->signer(), $this->configurations->signingKey())
            ->toString();
    }
}
