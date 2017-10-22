<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1701764f82c629b16cc164189600cb41
{
    public static $files = array (
        '5255c38a0faeba867671b61dfda6d864' => __DIR__ . '/..' . '/paragonie/random_compat/lib/random.php',
        'decc78cc4436b1292c6c0d151b19445c' => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'p' => 
        array (
            'phpseclib\\' => 10,
        ),
        'S' => 
        array (
            'SP\\Modules\\Web\\' => 15,
            'SP\\' => 3,
        ),
        'P' => 
        array (
            'Psr\\Container\\' => 14,
            'PHPMailer\\PHPMailer\\' => 20,
        ),
        'K' => 
        array (
            'Klein\\' => 6,
        ),
        'D' => 
        array (
            'Defuse\\Crypto\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'phpseclib\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib',
        ),
        'SP\\Modules\\Web\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app/modules/web',
        ),
        'SP\\' => 
        array (
            0 => __DIR__ . '/../..' . '/lib/SP',
        ),
        'Psr\\Container\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/container/src',
        ),
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
        'Klein\\' => 
        array (
            0 => __DIR__ . '/..' . '/klein/klein/src/Klein',
        ),
        'Defuse\\Crypto\\' => 
        array (
            0 => __DIR__ . '/..' . '/defuse/php-encryption/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'Pimple' => 
            array (
                0 => __DIR__ . '/..' . '/pimple/pimple/src',
            ),
        ),
        'B' => 
        array (
            'Base2n' => 
            array (
                0 => __DIR__ . '/..' . '/ademarre/binary-to-text-php',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1701764f82c629b16cc164189600cb41::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1701764f82c629b16cc164189600cb41::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit1701764f82c629b16cc164189600cb41::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}