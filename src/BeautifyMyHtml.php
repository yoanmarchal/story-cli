<?php
namespace Story\Cli;

class BeautifyMyHtml {

    /**
     * HTML-Tags which should not be processed.
     * Only tags with opening and closing tag does work: <example some="attributes">some content</example>
     * <img src="some.source" alt="" /> does not work because of the short end.
     * 
     * @var array
     */
    protected $tagsToIgnore = array (
            'script',
            'textarea',
            'pre',
            'style' 
    );

    /**
     * Code-Blocks which should not be processed are temporarily stored in this array.
     * 
     * @var array
     */
    protected $tagsToIgnoreBlocks = array ();

    /**
     * The tag to ignore at currently used runtime.
     * I had to define this in class and not local in method to get the
     * possibility to access this on anonymous function in preg_replace_callback.
     * 
     * @var string
     */
    protected $currentTagToIgnore;

    /**
     * Remove white-space before and after each line of blocks, which should not be processed?
     *
     * @var boolean
     */
    protected $trimTagsToIgnore = false;

    /**
     * Character used for indentation
     * 
     * @var string
     */
    protected $spaceCharacter = "\t";

    /**
     * Remove html-comments?
     *
     * @var boolean
     */
    protected $removeComments = false;

    /**
     * preg_replace()-Pattern which define opening tags to wrap with newlines.
     * <tag> becomes \n<tag>\n
     * 
     * @var array
     */
    protected $openTagsPattern = array (
            "/(<html\b[^>]*>)/i",
            "/(<head\b[^>]*>)/i",
            "/(<body\b[^>]*>)/i",
            "/(<link\b[^>]*>)/i",
            "/(<meta\b[^>]*>)/i",
            "/(<div\b[^>]*>)/i",
            "/(<section\b[^>]*>)/i",
            "/(<nav\b[^>]*>)/i",
            "/(<table\b[^>]*>)/i",
            "/(<thead\b[^>]*>)/i",
            "/(<tbody\b[^>]*>)/i",
            "/(<tr\b[^>]*>)/i",
            "/(<th\b[^>]*>)/i",
            "/(<td\b[^>]*>)/i",
            "/(<ul\b[^>]*>)/i",
            "/(<li\b[^>]*>)/i",
            "/(<figure\b[^>]*>)/i",
            "/(<select\b[^>]*>)/i" 
    );

    /**
     * preg_replace()-Pattern which define tags prepended with a newline.
     * <tag> becomes \n<tag>
     * 
     * @var array
     */
    protected $patternWithLineBefore = array (
            "/(<p\b[^>]*>)/i",
            "/(<h[0-9]\b[^>]*>)/i",
            "/(<option\b[^>]*>)/i" 
    );

    /**
     * preg_replace()-Pattern which define closing tags to wrap with newlines.
     * </tag> becomes \n</tag>\n
     * 
     * @var array
     */
    protected $closeTagsPattern = array (
            "/(<\/html>)/i",
            "/(<\/head>)/i",
            "/(<\/body>)/i",
            "/(<\/link>)/i",
            "/(<\/meta>)/i",
            "/(<\/div>)/i",
            "/(<\/section>)/i",
            "/(<\/nav>)/i",
            "/(<\/table>)/i",
            "/(<\/thead>)/i",
            "/(<\/tbody>)/i",
            "/(<\/tr>)/i",
            "/(<\/th>)/i",
            "/(<\/td>)/i",
            "/(<\/ul>)/i",
            "/(<\/li>)/i",
            "/(<\/figure>)/i",
            "/(<\/select>)/i" 
    );

    /**
     * preg_match()-Pattern with tag-names to increase indention.
     * 
     * @var string
     */
    protected $indentOpenTagsPattern = "/<(html|head|body|div|section|nav|table|thead|tbody|tr|th|td|ul|figure|li)\b[ ]*[^>]*[>]/i";

    /**
     * preg_match()-Pattern with tag-names to decrease indention.
     * 
     * @var string
     */
    protected $indentCloseTagsPattern = "/<\/(html|head|body|div|section|nav|table|thead|tbody|tr|th|td|ul|figure|li)>/i";

    /**
     * Constructor
     */
    public function __construct() {
    }

    /**
     * Adds a Tag which should be returned as the way in source.
     * 
     * @param string $tagToIgnore
     * @throws RuntimeException
     * @return void
     */
    public function addTagToIgnore($tagToIgnore) {
        if (! preg_match( '/^[a-zA-Z]+$/', $tagToIgnore )) {
            throw new RuntimeException( "Only characters from a to z are allowed as tag.", 1393489077 );
        }

        if (! in_array( $tagToIgnore, $this->tagsToIgnore )) {
            $this->tagsToIgnore[] = $tagToIgnore;
        }
    }

    /**
     * Setter for trimTagsToIgnore.
     *
     * @param boolean $bool
     * @return void
     */
    public function setTrimTagsToIgnore($bool) {
        $this->trimTagsToIgnore = $bool;
    }

    /**
     * Setter for removeComments.
     *  
     * @param boolean $bool
     * @return void
     */
    public function setRemoveComments($bool) {
        $this->removeComments = $bool;
    }

    /**
     * Callback function used by preg_replace_callback() to store the blocks which should be ignored and set a marker to replace them later again with the blocks.
     * 
     * @param array $e
     * @return string
     */
    private function tagsToIgnoreCallback($e) {
        // build key for reference
        $key = '<' . $this->currentTagToIgnore . '>' . sha1( $this->currentTagToIgnore . $e[0] ) . '</' . $this->currentTagToIgnore . '>';

        // trim each line
        if ($this->trimTagsToIgnore) {
            $lines = explode( "\n", $e[0] );
            array_walk( $lines, function (&$n) {
                $n = trim( $n );
            } );
            $e[0] = implode( PHP_EOL, $lines );
        }

        // add block to storage
        $this->tagsToIgnoreBlocks[$key] = $e[0];

        return $key;
    }

    /**
     * The main method.
     * 
     * @param string $buffer The HTML-Code to process
     * @return string The nice looking sourcecode
     */
    public function beautify($buffer) {
        // remove blocks, which should not be processed and add them later again using keys for reference 
        foreach ( $this->tagsToIgnore as $tag ) {
            $this->currentTagToIgnore = $tag;
            $buffer = preg_replace_callback( '/<' . $this->currentTagToIgnore . '\b[^>]*>([\s\S]*?)<\/' . $this->currentTagToIgnore . '>/mi', array (
                    $this,
                    'tagsToIgnoreCallback' 
            ), $buffer );
        }

        // temporarily remove comments to keep original linebreaks
        $this->currentTagToIgnore = 'htmlcomment';
        $buffer = preg_replace_callback( "/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/ms", array (
                $this,
                'tagsToIgnoreCallback' 
        ), $buffer );

        // cleanup source
        // ... all in one line
        // ... remove double spaces
        // ... remove tabulators
        $buffer = preg_replace( array (
                "/\s\s+|\n/",
                "/ +/",
                "/\t+/" 
        ), array (
                "",
                " ",
                "" 
        ), $buffer );

        // remove comments, if 
        if ($this->removeComments) {
            $buffer = preg_replace( "/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/ms", "", $buffer );
        }

        // add newlines for several tags
        $buffer = preg_replace( $this->patternWithLineBefore, "\n$1", $buffer ); // tags with line before tag
        $buffer = preg_replace( $this->openTagsPattern, "\n$1\n", $buffer ); // opening tags
        $buffer = preg_replace( $this->closeTagsPattern, "\n$1\n", $buffer ); // closing tags


        // get the html each line and do indention
        $lines = explode( "\n", $buffer );
        $indentionLevel = 0;
        $cleanContent = array (); // storage for indented lines
        foreach ( $lines as $line ) {
            // continue loop on empty lines
            if (! $line) {
                continue;
            }

            // test for closing tags
            if (preg_match( $this->indentCloseTagsPattern, $line )) {
                $indentionLevel --;
            }

            // push content
            $cleanContent[] = str_repeat( $this->spaceCharacter, $indentionLevel ) . $line;

            // test for opening tags
            if (preg_match( $this->indentOpenTagsPattern, $line )) {
                $indentionLevel ++;
            }
        }

        // write indented lines back to buffer
        $buffer = implode( PHP_EOL, $cleanContent );

        // add blocks, which should not be processed
        $buffer = str_replace( array_keys( $this->tagsToIgnoreBlocks ), $this->tagsToIgnoreBlocks, $buffer );

        return $buffer;
    }
}