<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
                           ->in(
                               [
                                   __DIR__.'/src',
                                   __DIR__.'/tests'
                               ]
                           );

return (new PhpCsFixer\Config())
                        ->setRiskyAllowed(true)
                        ->setUsingCache(false)
                        ->setRules(
                            [
                                '@Symfony' => true,
                                '@Symfony:risky' => true,
                                '@PSR1' => true,
                                '@PSR2' => true,
                                'array_syntax' => ['syntax' => 'short'],
                                'combine_consecutive_unsets' => true,
                                'declare_strict_types' => true,
                                'dir_constant' => true,
                                'ereg_to_preg' => false,
                                'header_comment' => false,
                                'heredoc_to_nowdoc' => true,
                                'is_null' => false,
                                'linebreak_after_opening_tag' => true,
                                'mb_str_functions' => true,
                                'modernize_types_casting' => true,
                                'native_function_invocation' => false,
                                'no_alias_functions' => false,
                                'blank_lines_before_namespace' => false,
                                'no_php4_constructor' => true,
                                'no_unreachable_default_argument_value' => true,
                                'no_useless_else' => true,
                                'no_useless_return' => true,
                                'not_operator_with_space' => false,
                                'not_operator_with_successor_space' => false,
                                'ordered_class_elements' => true,
                                'ordered_imports' => true,
                                'php_unit_construct' => false,
                                'php_unit_dedicate_assert' => false,
                                'php_unit_fqcn_annotation' => false,
                                'php_unit_strict' => false,
                                'phpdoc_add_missing_param_annotation' => false,
                                'phpdoc_order' => true,
                                'phpdoc_to_comment' => false,
                                'pow_to_exponentiation' => true,
                                'protected_to_private' => true,
                                'random_api_migration' => false,
                                'semicolon_after_instruction' => true,
                                'simplified_null_return' => false,
                                'strict_comparison' => false,
                                'strict_param' => true, // RISKY !
                                'ternary_to_null_coalescing' => false,
                            ]
                        )
                        ->setFinder($finder);
