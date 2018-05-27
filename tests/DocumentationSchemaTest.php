<?php

declare(strict_types=1);

namespace KevinGH\Box;

use function array_diff;
use function array_filter;
use function array_merge;
use function array_unique;
use Assert\Assertion;
use function hash_final;
use function json_decode;
use function KevinGH\Box\FileSystem\file_contents;
use PHPUnit\Framework\TestCase;
use function preg_match;
use function preg_match_all;
use const PREG_UNMATCHED_AS_NULL;
use function sort;

class DocumentationSchemaTest extends TestCase
{
    public function test_the_schema_keys_are_ordered_lexicographically()
    {
        $schemaKeys = $this->retrieveSchemaKeys();

        $expectedKeys = array_unique($schemaKeys);
        sort($expectedKeys);

        $this->assertSame($expectedKeys, $schemaKeys);
    }

    public function test_the_documentation_schema_is_up_to_date()
    {
        $docKeys = $this->retrieveDocSchemaKeys();
        $schemaKeys = $this->retrieveSchemaKeys();

        $this->assertSame($schemaKeys, $docKeys);
    }

    public function test_all_the_doc_keys_are_valid()
    {
        $docKeys = $this->retrieveDocKeys();

        $this->assertSame(
            array_unique($docKeys),
            $docKeys,
            'Did not expect to find duplicated keys in the documentation'
        );

        $schemaKeys = $this->retrieveSchemaKeys();

        $this->assertSame(
            [],
            array_diff($docKeys, $schemaKeys),
            'Did not expect to find a key in the documentation which is not found in the schema'
        );
    }

    public function test_there_is_a_doc_entry_for_each_schema_key()
    {
        $docKeys = $this->retrieveDocKeys();

        sort($docKeys);

        $schemaKeys = $this->retrieveSchemaKeys();

        $this->assertEquals($schemaKeys, $docKeys);
    }

    /**
     * @return string[]
     */
    private function retrieveDocSchemaKeys(): array
    {
        Assertion::same(
            1,
            preg_match(
                '/```json(?<schema>.*?)```/s',
                file_contents(__DIR__.'/../doc/configuration.md'),
                $matches
            )
        );

        return array_keys(json_decode($matches['schema'], true));
    }

    /**
     * @return string[]
     */
    private function retrieveSchemaKeys(): array
    {
        $schema = json_decode(
            file_contents(__DIR__.'/../res/schema.json'),
            true
        );

        return array_keys($schema['properties']);
    }

    /**
     * @return string[]
     */
    private function retrieveDocKeys(): array
    {
        preg_match_all(
            '/#+ [\p{L}-\s]+\(`(.*?)`(?:[\p{L}-\s]+`(.*?)`)?\)/',
            file_contents(__DIR__.'/../doc/configuration.md'),
            $matches,
            PREG_UNMATCHED_AS_NULL
        );

        return array_filter(
            array_merge(
                $matches[1],
                $matches[2]
            )
        );
    }
}
