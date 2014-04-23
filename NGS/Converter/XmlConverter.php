<?php
namespace NGS\Converter;

require_once(__DIR__.'/../Utils.php');

use InvalidArgumentException;
use NGS\Utils;

/**
 * Converts values to SimpleXmlElement php type
 */
abstract class XmlConverter
{
    public static function toXml($value)
    {
        if($value instanceof \SimpleXMLElement)
            return $value;
        if(is_string($value))
            return new \SimpleXMLElement($value);
        if(is_array($value))
            return self::build_xml($value);
        throw new InvalidArgumentException('Could not convert value '.$value.' of type "'.Utils::getType($value).'" to xml!');
    }

    public static function toXmlArray(array $items, $allowNullValues=false)
    {
        $results = array();
        try {
            foreach ($items as $key => $val) {
                if ($allowNullValues && $val===null) {
                    $results[] = null;
                } elseif ($val === null) {
                    throw new InvalidArgumentException('Null value found in provided array');
                } else {
                    $results[] = self::toXml($val);
                }
            }
        }
        catch (\Exception $e) {
            throw new \InvalidArgumentException('Element at index '.$key.' could not be converted to xml!', 42, $e);
        }
        return $items;
    }

    public static function toArray($value)
    {
        if($value instanceof \SimpleXMLElement)
            return self::toArrayObject($value);
        $result = array();
        foreach($value as $key => $item) {
            try {
                if($item === null)
                    throw new InvalidArgumentException('Null value found in provided array');
                $item = self::toArrayObject($item);
            }
            catch(\Exception $e) {
                throw new \InvalidArgumentException('Element at index '.$key.' could not be converted to xml array!', 42, $e);
            }
            $result[$key] = $item;
        }
        return $result;
    }

    private static function build_xml(array $arr)
    {
        $keys = array_keys($arr);
        $name = $keys[0];
        $root = $arr[$name];

        if (is_array($root) && array_key_exists('#text', $root)) {
            $text = $root['#text'];
        } else if (is_string($root)) {
            $text = $root;
        } else {
            $text = '';
        }

        $str = '<'.$name.'>'.$text.'</'.$name.'>';
        $xml = new \SimpleXMLElement($str);

        if(is_array($root))
            self::array_to_xml($root, $xml);

        return $xml;
    }

    private static function array_to_xml(array $arr, &$xml)
    {
        foreach($arr as $key => $value) {
            if(strpos($key, '@', 0) === 0) {
                $xml->addAttribute(substr($key, 1), $value);
            } else if(is_array($value)) {
                $child_has_only_numeric_keys = true;
                $i = 0;
                foreach($value as $k=>$v)
                    if($k!==$i++)
                        $child_has_only_numeric_keys = false;

                // addChild does not escape ampersands and left angle bracket
                // this is specified behaviour
                // @see https://bugs.php.net/bug.php?id=45253
                if($child_has_only_numeric_keys) {
                    foreach($value as $k=>$v) {
                        $subnode = null;
                        if (is_array($v)) {
                            if (array_key_exists('#text', $v)) {
                                $subnode = $xml->addChild("$key", str_replace(array('&', '<'), array('&amp;', '&lt;'), $v['#text']));
                            } else {
                                $subnode = $xml->addChild("$key");
                            }
                            if ($subnode!==null) {
                                self::array_to_xml($v, $subnode);
                            }
                        } else if (is_string($v)) {
                            $xml->addChild("$key", str_replace(array('&', '<'), array('&amp;', '&lt;'), $v));
                        } else {
                            $xml->addChild("$key");
                        }
                    }
                } else if(!is_numeric($key)) {
                    $subnode = array_key_exists('#text', $value)
                        ? $xml->addChild("$key", str_replace(array('&', '<'), array('&amp;', '&lt;'), $value['#text']))
                        : $xml->addChild("$key");
                    self::array_to_xml($value, $subnode);
                } else {
                    self::array_to_xml($value, $xml);
                }
            } else if($key !== '#text')
                $xml->$key = "$value";
        }
    }

    private static function xml_to_array(\DOMNode $xml, array &$arr)
    {
       $attributes = $xml->attributes;
       $children = $xml->childNodes;

       $childrenNodesCount = count($children);
       $attributeNodesCount = count($attributes);

       if(!$xml->hasChildNodes() && !$xml->hasAttributes()){
         $arr[]=null;
         return;
       }

       if($xml->hasAttributes())
         foreach($attributes as $attr)
           $arr['@'.$attr->nodeName] = $attr->nodeValue;

       foreach($children as $child)
       {
          $name = $child->nodeName;

          if($child->nodeType === XML_TEXT_NODE){
            $textContent=trim($child->textContent);
            if(strlen($textContent)>0){
              if(isset($arr["#text"]))
                $arr["#text"].=$textContent;
              else
              $arr["#text"]=$textContent;
            }
          }
          else if(!isset($arr[$name])){
            self::xml_to_array($child,$arr);
          }
          else if (!is_array($arr[$name])){
            $node = clone $arr[$name];
            $arr[$name]=array();
            $arr[$name][]=$node;
            self::xml_to_array($child,$arr[$name]);
          }
          else{
            self::xml_to_array($child,$arr[$name]);
          }
      }
    }

    /**
     * Returns the converted Json array for the SimpleXMLElement $value
     */
    private static function toArrayObject(\SimpleXMLElement $value)
    {
      $node = dom_import_simplexml($value);

      self::trimWhitespaceTextNodes($node);

      if(is_null($value)) return NULL;

      if(count($node->childNodes)==0){
        $jsonArray=array($node->nodeName => $node->textContent);
        return $jsonArray;
      }
      else{
        $namespacesContextStack=array();
        $jsonArray = array($node->nodeName => self::build_from_xml($node, $namespacesContextStack));

        return $jsonArray;
      }
    }

    /**
     * Builds a Json array from the given DOMNode $node
     *
     * @param $node The root node of the current DOM subtree
     * @param $namespaceContext all namespaces inherited from the parent context
     */
    private static function build_from_xml(\DOMNode $node, $namespaceContext){

      $jsonArray = array();

      $children = $node->childNodes;

      /* Collect the attributes into an array */
      $attributes=array();
      foreach($node->attributes as $attribute)
        $attributes[$attribute->nodeName] = $attribute->nodeValue;

      /* $node->attributes does not retrieve the xmlns attributes, so we need to append them manually */
      $namespaces_declaredOnNode = simplexml_import_dom($node)->getDocNamespaces(false,false);
      if(count($namespaces_declaredOnNode)>0)
        foreach($namespaces_declaredOnNode as $key=>$value){
          /* Namespaces already in the current context are declared in an ancestor node, do not add them unless we are redefining the namespace */
            if(!isset($namespaceContext[$key])
                ||(isset($namespaceContext[$key]) && $namespaceContext[$key] !== $value)){
              if($key==="")
                  $attributes["xmlns"] = $value;
              else
                $attributes["xmlns:$key"] = $value;
              $namespaceContext[$key]=$value;
            }
        }

      /* If there are no children and attributes, just return the node's text value */
      if(count($children)===0 && count($attributes)===0){
        $txt = $node->textContent;
        if($txt==="") $jsonArray=NULL; else $jsonArray=$txt;
      }

      /* Sort the nodes by children names */
      $childrenByName=array();
      foreach($children as $child){
        $name=$child->nodeName;

        if(!isset($childrenByName[$name]))
          $childrenByName[$name]=array();

        $childrenByName[$name][]=$child;
      }

      /* Serialize the attributes */
      foreach($attributes as $name=>$value){
        $jsonArray["@".$name]=$value;
      }

      /*
       * Put the child nodes into the array; Nodes are sorted by name, nodes
       * having the same name are put together into an array
       */
      foreach($childrenByName as $name => $children){
        $items = array();
        foreach($children as $child){
          if($child->nodeType === XML_ELEMENT_NODE){
            $items[]=self::build_from_xml($child, $namespaceContext);
          }
          else if($child->nodeType ===XML_TEXT_NODE && $child->nodeValue ==="")
          {/* do not serialize this*/}
          else
            //$items[]=is_null($child->nodeValue)? "" : $child->nodeValue;
            $items[]=$child->nodeValue; // TODO, see if this makes any difference; some NULL nodes were serialised as array(0) instead of NULL
        }

        /* Insert the resulting object into the return value array */
        if(count($items)>1)
          $jsonArray[$name]=$items;
        else if(count($items)==1)
          $jsonArray[$name]=$items[key($items)]; // first element of items
        else if(count($items)==0)
        {/* Append nothing */}
      }

      /* Single #text nodes are just text */
      if(count($attributes) === 0 && count($jsonArray) === 1 && key($jsonArray)==="#text")
        $jsonArray=current($jsonArray);

      /* Zero-sized arrays are in fact NULLs*/
      if(count($jsonArray)===0)
        $jsonArray=NULL;

      return $jsonArray;
    }

    /**
     * Trims whitespace from text nodes of $root, otherwise
     * the converted Json would have extra text nodes
     */
    private static function trimWhiteSpaceTextNodes (\DOMNode $root){

      if($root->nodeType === XML_TEXT_NODE
          && ctype_space($root->nodeValue))
      {
        $root->nodeValue = trim($root->nodeValue);
      }

      if($root->hasChildNodes())
        foreach($root->childNodes as $child)
          self::trimWhiteSpaceTextNodes($child);
    }

    /**
     * Returns an map of $name => $children_group,
     * grouping nodes in $children by name
     */
    private static function childrenGroupedByName(\DOMNodeList $children){

      $groupedByName=array();

      foreach ($children as $child){
        $name = $child->nodeName;

        if(!isset($groupedByName[$name]))
          $groupedByName[$name]=array();

        $groupedByName[$name][]=$child;
      }

      return $groupedByName;
    }

}

