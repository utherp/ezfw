<?php
    define('DEFAULT_ITERATOR_LIMIT', 20);

    class ezIterator {
        private $last_identifier = false;
        private $index;
        private $order;
        private $desc = false;
        private $limit;
        private $clause;
        private $vals;
        private $buffer;
        private $obj;
        public $static_list;

        function __construct ($type, $clause = '', $vals = NULL, $index = 0, $order = '', $limit = DEFAULT_ITERATOR_LIMIT) {
            if (is_a($type, 'ezObj'))
                $this->obj = $type;
            else {
                $this->obj = new $type();
                if (!is_a($this->obj, 'ezObj'))
                    return;
            }

            if (is_array($clause)) {
                $this->static_list = true;
                $this->buffer = $clause;
                return;
            }

            $this->clause = $clause;
            $this->vals = isset($vals)?(is_array($vals)?$vals:array($vals)):array();
            $this->index = is_numeric($index)?$index:0;
            $this->order = is_string($order)?$order:'';
            $this->limit = is_numeric($limit)?$limit:DEFAULT_ITERATOR_LIMIT;

            $this->buffer = array();

            if (!$this->order)
                $this->order = $this->obj->identifier_field();
            else if (strpos($this->order, ' desc')) {
                $this->order = preg_replace('/ desc/', '', $this->order);
                $this->desc = true;
            } else
                $this->order = preg_replace('/ asc/', '', $this->order);

            return;
        }

        public function reset() { 
            $this->index = 0;
            while (count($this->buffer))
                array_pop($this->buffer);
            return;
        }

        /******************************************
            current returns a ref to the next in
            the buffer, but doesn't shift it.
            next shifts it off and returns it
        */
        public function &current () {
            $vid = NULL;
            if (!count($this->buffer) && !$this->refill_buffer())
                return $vid;
            $vid =& $this->buffer[0];
            return $vid;
        }

        public function next () {
            if (!count($this->buffer) && !$this->refill_buffer())
                return NULL;

            return array_shift($this->buffer);
        }

        // refil video buffer with next REFILL_COUNT videos starting at $video_index
        private function refill_buffer () {
            if ($this->static_list) return false;
            $clause = '( ' . $this->clause . ' )';
            if ($this->last_identifier !== false) {
                $clause .= ' AND ' . $this->order . ($this->desc?' <= ?':' >= ?');
                $vals = array_merge($this->vals, (array)$this->last_identifier);
            } else
                $vals =& $this->vals;
            
            $res = $this->obj->load_all($clause, $vals, $this->index . ',' . $this->limit, $this->order . ($this->desc?' desc':''));

            $c = count($res);
            foreach ($res as $obj) {
                array_push($this->buffer, $obj);
                $ordered = $obj->_get($this->order, true);
                if ($ordered !== $this->last_identifier) {
                    $this->last_identifier = $ordered;
                    $this->index = 1;
                } else
                    $this->index++;
            }

            return $c;
        }

    }
