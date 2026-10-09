<?php

declare(strict_types=1);

namespace App\Fill;

/**
 * Plain text out of a Word (.docx) file, for sending to Gemini, which reads
 * PDFs itself but not Word files. A .docx is a zip of XML parts; production
 * PHP has no zip extension, so this walks the zip's central directory by
 * hand and inflates word/document.xml with zlib.
 */
class DocumentText
{
    /** Refuse anything that inflates past this — a guard against zip bombs. */
    private const MAX_XML_BYTES = 20 * 1024 * 1024;

    /** @throws DocumentFillException */
    public static function fromDocx(string $bytes): string
    {
        $xml = self::zipEntry($bytes, 'word/document.xml');
        if ($xml === null) {
            throw new DocumentFillException('That file isn\'t a Word document (.docx). Older .doc files: save as .docx or PDF first.');
        }

        return self::wordXmlToText($xml);
    }

    /**
     * Paragraphs become lines and table cells are separated by " | ", so a
     * pronunciation guide laid out as a table keeps its rows together.
     */
    public static function wordXmlToText(string $xml): string
    {
        // Raw line breaks are only formatting between tags (text breaks are <w:br/>).
        $xml = str_replace(["\r", "\n"], '', $xml);
        $text = preg_replace(
            ['#</w:p>\s*</w:tc>#', '#<w:tab/>#', '#<w:br[^>]*/>#', '#</w:tc>#', '#</w:p>#', '#</w:tr>#'],
            ['</w:tc>', "\t", "\n", ' | ', "\n", "\n"],
            $xml
        );
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_XML1, 'UTF-8');
        // Tidy cell separators left at line ends and runs of blank lines.
        $text = preg_replace(["#[ \t]*\\|[ \t]*\n#", "#\n{3,}#"], ["\n", "\n\n"], $text);

        return trim((string) $text);
    }

    /** One file's contents from a zip archive, or null when it isn't there. */
    private static function zipEntry(string $zip, string $name): ?string
    {
        // End of central directory: the last "PK\5\6" within the final 64 KB + 22.
        $eocd = strrpos(substr($zip, -65557), "PK\x05\x06");
        if ($eocd === false) {
            return null;
        }
        $eocd += max(0, strlen($zip) - 65557);
        $end = unpack('vdisk/vcdDisk/ventriesHere/ventries/VcdSize/VcdOffset', substr($zip, $eocd + 4, 16));
        if ($end === false) {
            return null;
        }

        $pos = $end['cdOffset'];
        for ($i = 0; $i < $end['entries']; $i++) {
            if (substr($zip, $pos, 4) !== "PK\x01\x02") {
                return null;
            }
            $entry = unpack(
                'vmadeBy/vneeded/vflags/vmethod/vtime/vdate/Vcrc/VcompSize/VsizeOut/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
                substr($zip, $pos + 4, 42)
            );
            if ($entry === false) {
                return null;
            }
            $entryName = substr($zip, $pos + 46, $entry['nameLen']);
            $pos += 46 + $entry['nameLen'] + $entry['extraLen'] + $entry['commentLen'];
            if ($entryName !== $name) {
                continue;
            }

            $local = $entry['localOffset'];
            if (substr($zip, $local, 4) !== "PK\x03\x04") {
                return null;
            }
            $header = unpack('vnameLen/vextraLen', substr($zip, $local + 26, 4));
            $data = substr($zip, $local + 30 + $header['nameLen'] + $header['extraLen'], $entry['compSize']);
            if ($entry['method'] === 0) {
                return $data;
            }
            if ($entry['method'] !== 8 || $entry['sizeOut'] > self::MAX_XML_BYTES) {
                return null;
            }
            $inflated = @gzinflate($data, self::MAX_XML_BYTES);

            return $inflated === false ? null : $inflated;
        }

        return null;
    }
}
