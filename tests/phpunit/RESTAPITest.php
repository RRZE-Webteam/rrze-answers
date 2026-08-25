<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use WP_REST_Request;
use WP_UnitTestCase;

final class RESTAPITest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        rest_get_server();
    }

    /** @dataProvider synchronizedPostTypes */
    public function testEntryResponseProvidesNativeIdsAndCollisionFreeTermNames(
        string $contentType
    ): void {
        $postType = 'rrze_' . $contentType;
        $categoryTaxonomy = $postType . '_category';
        $tagTaxonomy = $postType . '_tag';
        $category = wp_insert_term('Source category', $categoryTaxonomy);
        $tag = wp_insert_term('Source tag', $tagTaxonomy);

        self::assertIsArray($category);
        self::assertIsArray($tag);

        $postId = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'REST contract entry',
            'meta_input' => [
                'source' => 'website',
                'lang' => 'de',
                'remoteID' => 410,
                'remoteChanged' => 123456,
            ],
        ]);
        wp_set_object_terms($postId, [(int) $category['term_id']], $categoryTaxonomy);
        wp_set_object_terms($postId, [(int) $tag['term_id']], $tagTaxonomy);

        $request = new WP_REST_Request('GET', '/wp/v2/' . $contentType);
        $request->set_param('include', [$postId]);
        $request->set_param('_embed', 'wp:term');
        $response = rest_do_request($request);
        $collectionData = rest_get_server()->response_to_data($response, ['wp:term']);

        self::assertSame(200, $response->get_status());
        self::assertCount(1, $collectionData);
        $responseData = $collectionData[0];
        self::assertSame([(int) $category['term_id']], $responseData[$categoryTaxonomy]);
        self::assertSame([(int) $tag['term_id']], $responseData[$tagTaxonomy]);
        self::assertSame(['Source category'], $responseData['rrze_answers_category_names']);
        self::assertSame(['Source tag'], $responseData['rrze_answers_tag_names']);
        self::assertSame('website', $responseData['source']);
        self::assertSame(410, (int) $responseData['remoteID']);
        self::assertSame(123456, (int) $responseData['remoteChanged']);
        self::assertSame(
            ['Source category', 'Source tag'],
            $this->getEmbeddedTermNames($responseData)
        );
    }

    /** @return array<string, array{string}> */
    public static function synchronizedPostTypes(): array
    {
        return [
            'FAQ' => ['faq'],
            'Glossary' => ['glossary'],
        ];
    }

    /**
     * @param array<string, mixed> $responseData
     * @return string[]
     */
    private function getEmbeddedTermNames(array $responseData): array
    {
        $termNames = [];

        foreach ($responseData['_embedded']['wp:term'] ?? [] as $termGroup) {
            foreach ($termGroup as $term) {
                if (isset($term['name'])) {
                    $termNames[] = (string) $term['name'];
                }
            }
        }

        sort($termNames);

        return $termNames;
    }
}
