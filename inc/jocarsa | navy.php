<?php
/**
 * md_parser.php
 * 
 * Receives AJAX calls with 'ajax_markdown' via POST,
 * calls markdownToHtml(), and returns the HTML.
 */

/**
 * Define the conversion function here
 */
function markdownToHtml($markdown) {
    // We'll use a special encoding to protect content that shouldn't be processed
    // These are more unlikely characters to appear in normal text
    $CODE_BLOCK_PREFIX = "\x02\x02\x02CODEBLOCK\x02\x02\x02";
    $INLINE_CODE_PREFIX = "\x02\x02\x02INLINECODE\x02\x02\x02";
    
    // -------------------------------------------------------------
    // 1) EXTRACT CODE BLOCKS
    // -------------------------------------------------------------
    $codeBlocks = [];
    $markdown = preg_replace_callback(
        '/```([^\r\n]*)(?:\r?\n([\s\S]*?))?```/',
        function($matches) use (&$codeBlocks, $CODE_BLOCK_PREFIX) {
            // If there's a newline, use the first capture group as language and the second as code
            if (isset($matches[2])) {
                $lang = trim($matches[1]);
                $content = $matches[2];
            } else {
                // No newline: split on the first space
                $parts = preg_split('/\s+/', trim($matches[1]), 2);
                $lang = isset($parts[0]) ? trim($parts[0]) : '';
                $content = isset($parts[1]) ? $parts[1] : '';
            }

            if (in_array(strtolower($lang), ['mermaid', 'plantuml', 'graphviz'])) {
                // Special diagram support
                $htmlCode = '<div class="'.strtolower($lang).'">'.htmlspecialchars($content).'</div>';
            } else {
                // Normal code block
                $classAttr = $lang ? ' class="language-'.strtolower($lang).'"' : '';
                $htmlCode = '<pre'.$classAttr.'><code>'.htmlspecialchars($content).'</code></pre>';
            }

            $index = count($codeBlocks);
            $placeholder = $CODE_BLOCK_PREFIX . $index . $CODE_BLOCK_PREFIX;
            $codeBlocks[$placeholder] = $htmlCode;
            return $placeholder;
        },
        $markdown
    );

    // -------------------------------------------------------------
    // 2) EXTRACT INLINE CODE
    // -------------------------------------------------------------
    $inlineCodes = [];
    $markdown = preg_replace_callback(
        '/`([^`]+)`/',
        function ($matches) use (&$inlineCodes, $INLINE_CODE_PREFIX) {
            $index = count($inlineCodes);
            $placeholder = $INLINE_CODE_PREFIX . $index . $INLINE_CODE_PREFIX;
            $inlineCodes[$placeholder] = '<code>'.htmlspecialchars($matches[1]).'</code>';
            return $placeholder;
        },
        $markdown
    );

    // -------------------------------------------------------------
    // 3) HEADERS
    // -------------------------------------------------------------
    $markdown = preg_replace('/^###### (.*)$/m', '<h6>$1</h6>', $markdown);
    $markdown = preg_replace('/^##### (.*)$/m',  '<h5>$1</h5>', $markdown);
    $markdown = preg_replace('/^#### (.*)$/m',   '<h4>$1</h4>', $markdown);
    $markdown = preg_replace('/^### (.*)$/m',    '<h3>$1</h3>', $markdown);
    $markdown = preg_replace('/^## (.*)$/m',     '<h2>$1</h2>', $markdown);
    $markdown = preg_replace('/^# (.*)$/m',      '<h1>$1</h1>', $markdown);

    // -------------------------------------------------------------
    // 4) BOLD AND ITALIC
    // -------------------------------------------------------------
    // Bold
    $markdown = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $markdown);
    $markdown = preg_replace('/__(.*?)__/s',     '<strong>$1</strong>', $markdown);

    // Italic
    $markdown = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $markdown);
    $markdown = preg_replace('/_(.*?)_/s',   '<em>$1</em>', $markdown);

    // -------------------------------------------------------------
    // 5) STRIKETHROUGH
    // -------------------------------------------------------------
    $markdown = preg_replace('/~~(.*?)~~/s', '<del>$1</del>', $markdown);

    // -------------------------------------------------------------
    // 6) IMAGES
    // -------------------------------------------------------------
    $markdown = preg_replace(
        '/!\[([^\]]*)\]\(([^) ]+)(?: "([^"]+)")?\)/',
        '<img src="$2" alt="$1" title="$3" />',
        $markdown
    );

    // -------------------------------------------------------------
    // 7) LINKS
    // -------------------------------------------------------------
    $markdown = preg_replace(
        '/\[([^\]]+)\]\(([^) ]+)(?: "([^"]+)")?\)/',
        '<a href="$2" title="$3">$1</a>',
        $markdown
    );

    // -------------------------------------------------------------
    // 8) BLOCKQUOTES
    // -------------------------------------------------------------
    $markdown = preg_replace('/^> (.*)$/m', '<blockquote>$1</blockquote>', $markdown);

    // -------------------------------------------------------------
    // 9) TASK LISTS
    // -------------------------------------------------------------
    $markdown = preg_replace_callback(
        '/^(?:[\-\*]\s\[[x ]\].*\R)+/m',
        function ($matches) {
            $lines = preg_split('/\R/', trim($matches[0]));
            $html  = "<ul>\n";
            foreach ($lines as $line) {
                if (preg_match('/^[\-\*]\s\[([x ])\]\s(.*)$/', $line, $parts)) {
                    $checked = ($parts[1] === 'x') ? 'checked' : '';
                    $text    = $parts[2];
                    $html   .= "  <li><input type=\"checkbox\" $checked disabled /> $text</li>\n";
                }
            }
            $html .= "</ul>\n";
            return $html;
        },
        $markdown
    );

    // -------------------------------------------------------------
    // 10) ORDERED LISTS
    // -------------------------------------------------------------
    $markdown = preg_replace_callback(
        '/^(?:\d+\.\s.*\R)+/m',
        function ($matches) {
            $lines = preg_split('/\R/', trim($matches[0]));
            $html  = "<ol>\n";
            foreach ($lines as $line) {
                $item = preg_replace('/^\d+\.\s/', '', $line);
                $html .= "  <li>$item</li>\n";
            }
            $html .= "</ol>\n";
            return $html;
        },
        $markdown
    );

    // -------------------------------------------------------------
    // 11) UNORDERED LISTS
    // -------------------------------------------------------------
    $markdown = preg_replace_callback(
        '/^(?:[\-\*]\s(?!\[).*\R)+/m',
        function ($matches) {
            $lines = preg_split('/\R/', trim($matches[0]));
            $html  = "<ul>\n";
            foreach ($lines as $line) {
                $item = preg_replace('/^[\-\*]\s/', '', $line);
                $html .= "  <li>$item</li>\n";
            }
            $html .= "</ul>\n";
            return $html;
        },
        $markdown
    );

    // -------------------------------------------------------------
    // 12) TABLES
    // -------------------------------------------------------------
    $markdown = preg_replace_callback(
        '/^(\|.+\|)\R(\|[ \-:\|]+)\R((?:\|.+\|\R?)+)/m',
        function ($matches) {
            $headerLine = trim($matches[1]);
            $rowsText   = trim($matches[3]);

            // Headers
            $headerCells = explode('|', trim($headerLine, '| '));
            $thead = "<thead>\n  <tr>";
            foreach ($headerCells as $cell) {
                $thead .= "<th>".trim($cell)."</th>";
            }
            $thead .= "</tr>\n</thead>";

            // Rows
            $rowLines = preg_split('/\R/', $rowsText);
            $tbody = "<tbody>\n";
            foreach ($rowLines as $rowLine) {
                $rowLine = trim($rowLine);
                if (!$rowLine) continue;
                $cells = explode('|', trim($rowLine, '| '));
                $tbody .= "  <tr>";
                foreach ($cells as $cell) {
                    $tbody .= "<td>".trim($cell)."</td>";
                }
                $tbody .= "</tr>\n";
            }
            $tbody .= "</tbody>";

            return "<table>\n$thead\n$tbody\n</table>\n";
        },
        $markdown
    );

    // -------------------------------------------------------------
    // 13) HORIZONTAL RULES
    // -------------------------------------------------------------
    $markdown = preg_replace('/^([-*]){3,}$/m', '<hr />', $markdown);

    // -------------------------------------------------------------
    // 14) PARAGRAPHS
    // -------------------------------------------------------------
    $chunks = preg_split('/\n\s*\n/', trim($markdown));
    $markdown = '';
    foreach ($chunks as $chunk) {
        $trimmed = trim($chunk);
        
        // Special handling for code block placeholders - don't wrap them
        if (preg_match('/^' . preg_quote($CODE_BLOCK_PREFIX) . '\d+' . preg_quote($CODE_BLOCK_PREFIX) . '$/', $trimmed)) {
            $markdown .= $trimmed . "\n\n";
        } 
        // Don't wrap content that starts with block-level HTML tags
        elseif (!preg_match('/^(<(h[1-6]|ul|ol|blockquote|pre|hr|table|div|thead|tbody|tr|th|td))/i', $trimmed)) {
            $markdown .= '<p>' . $trimmed . "</p>\n\n";
        } 
        else {
            $markdown .= $trimmed . "\n\n";
        }
    }

    // -------------------------------------------------------------
    // 15 & 16) REPLACE CODE PLACEHOLDERS
    // -------------------------------------------------------------
    // Replace inline code placeholders with actual HTML
    foreach ($inlineCodes as $placeholder => $html) {
        $markdown = str_replace($placeholder, $html, $markdown);
    }
    
    // Replace code block placeholders with actual HTML
    foreach ($codeBlocks as $placeholder => $html) {
        $markdown = str_replace($placeholder, $html, $markdown);
    }

    return $markdown;
}

/**
 * If we see POST['ajax_markdown'], convert and return HTML.
 */
if (isset($_POST['ajax_markdown'])) {
    $md   = $_POST['ajax_markdown'];
    $html = markdownToHtml($md);
    echo $html;
    exit;
}
else {
    // For testing purposes
    $test = "Testing the parser with a code block:

```sql
CREATE DATABASE empresa;
```

And here's some **bold** and *italic* text after it.";
    
    echo "<h3>Original markdown:</h3>";
    echo "<pre>" . htmlspecialchars($test) . "</pre>";
    
    echo "<h3>Rendered HTML:</h3>";
    $rendered = markdownToHtml($test);
    echo $rendered;
    
    echo "<h3>HTML source:</h3>";
    echo "<pre>" . htmlspecialchars($rendered) . "</pre>";
}
?>
