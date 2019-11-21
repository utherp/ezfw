<?php
	// web/common.php
	// analogizing pear HTML_Common
	class web_common {
		protected $strHTML = '';
		protected $tab = "\11";			//tab "\t"
		protected $nl = "\12";			//newline "\n"
		
		/**
	     * Abstract method.  Must be extended to return the objects HTML
	     *
	     * @access    public
	     * @return    string
	     * @abstract
	     */
	    public function toHtml() {
	    	return $this->strHtml;
	    }
	    
	    /**
	     * Displays the HTML to the screen
	     *
	     * @access    public
	     */
	    public function display()
	    {
	        print $this->toHtml();
	    } // end func display
	}
?>
