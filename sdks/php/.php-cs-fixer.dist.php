<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                       => true,
        '@PSR12:risky'                 => true,
        'array_syntax'                 => ['syntax' => 'short'],
        'binary_operator_spaces'       => [
            'default' => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal'],
        ],
        'no_unused_imports'            => true,
        'ordered_imports'              => ['sort_algorithm' => 'alpha'],
        'single_quote'                 => true,
        'trailing_comma_in_multiline'  => ['elements' => ['arrays', 'arguments']],
        'declare_strict_types'         => true,
        'native_function_invocation'   => false,
    ])
    ->setFinder($finder);
