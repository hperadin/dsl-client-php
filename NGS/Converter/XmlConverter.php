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

    private static function build_xml(array $rootArray)
    {

        if(count($rootArray) > 1){
          die("Single root element required.");
        }

        $name = key($rootArray);
        $root = current($rootArray);

        // TODO: See if all is well with namespaces
        $doc = new \DOMDocument();
        $xml = $doc->createElement($name);
        self::buildXmlFromJsonElement($doc, $xml, $root);

        $doc->appendChild($xml);

//         print "===\n";
//         print "XML converted: \n";
//         print($doc->saveXML());
//         print "===\n";

        return simplexml_import_dom($xml);
    }

    private static function buildXmlFromJsonElement(\DOMDocument &$doc, \DOMElement &$root, $json){
        if(is_array($json)){
          foreach($json as $childKey => $childValue){
            if(self::startsWith('@', $childKey)){
              $attr = $doc->createAttribute(substr($childKey, 1));
              $attr->nodeValue=$childValue;
              $root->appendChild($attr);
//               print("Added attribute: $childKey:$childValue\n");
            }
            else if(self::startsWith('#text', $childKey)){
              $txt = $doc->createTextNode($childValue);
              $root->appendChild($txt);
//               print("Added text node: $childKey:$childValue\n");
            }
            else if(self::startsWith('#cdata', $childKey)){
              $cData = $doc->createCDATASection($childValue);
              $root->appendChild($cData);
              //               print("Added cData node: $childKey:$childValue\n");
            }
            else if(self::startsWith('#comment', $childKey)){
              $comment = $doc->createComment($childValue);
              $root->appendChild($comment);
              //               print("Added comment node: $childKey:$childValue\n");
            }
            else if(is_array($childValue)){
              if(self::hasOnlyNumericKeys($childValue)){
//                 print("Doing array node: $childKey\n");
                self::buildXmlFromJsonArray($doc, $root, $childKey, $childValue);
              }else{
//                 print("Doing a normal child node: $childKey\n");
                $child = $doc->createElement($childKey);
                $root->appendChild($child);
                self::buildXmlFromJsonElement($doc, $child, $childValue);
              }
            }
            else{
//               print("Doing a non-array child node: $childKey\n");
              $child = $doc->createElement($childKey);
                $root->appendChild($child);
                self::buildXmlFromJsonElement($doc, $child, $childValue);
            }
          }
        }else{
//           print("Doing a non-array node: $json\n");
          $root->nodeValue = $json;
        }
    }

    private static function buildXmlFromJsonArray(\DOMDocument &$doc, \DOMElement &$parent, $collectiveName, array $json){
      foreach ($json as $key=>$value){
//         print("Doing an array node: $key:$collectiveName\n");
        $element = $doc -> createElement($collectiveName);
        $parent -> appendChild($element);
        self::buildXmlFromJsonElement($doc, $element, $value);
      }
    }

    private static function array_to_xml(array $arr, &$xml)
    {
      foreach($arr as $childKey => $childValue) {
            if(self::startsWith('@', $childKey)){
                $xml->addAttribute(substr($childKey, 1), $childValue);
            } else if(is_array($childValue)) {
                // addChild does not escape ampersands and left angle bracket
                // this is specified behaviour
                // @see https://bugs.php.net/bug.php?id=45253
                if(self::hasOnlyNumericKeys($childValue)) {
                    foreach($childValue as $grandchildKey=>$grandchildValue) {
                        $subnode = null;
                        if (is_array($grandchildValue)) {

                            $subnode = array_key_exists('#text', $grandchildValue)
                              ? $xml->addchild("$childKey", self::escapeXml($grandchildValue['#text']))
                              : $xml->addChild("$childKey");

                            if ($subnode!==null) {
                                self::array_to_xml($grandchildValue, $subnode);
                            }

                        } else if (is_string($grandchildValue)) {
                            $xml->addChild("$childKey", self::escapeXml($grandchildValue));
                        } else {
                            $xml->addChild("$childKey");
                        }
                    }
                } else if(!is_numeric($childKey)) {
                    $subnode = array_key_exists('#text', $childValue)
                        ? $xml->addchild("$childKey", self::escapeXml($childValue['#text']))
                        : $xml->addChild("$childKey");
                    self::array_to_xml($childValue, $subnode);
                } else {
                    /* We skip the deserialisation of numeric keys, since they are just elements?
                     * TODO: check if maybe these should be deserialised having node name as key from parent array... */
                    self::array_to_xml($childValue, $xml);
                }
                // TODO: #cdata perhaps, and the rest of the stuff
            } else if($childKey !== '#text')
                $xml->$childKey = "$childValue";
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
    private static function build_from_xml(\DOMNode $node){

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
              if($key==="")
                  $attributes["xmlns"] = $value;
              else
                $attributes["xmlns:$key"] = $value;
        }

      /* If there are no children and attributes, just return the node's text value (or NULL) */
      if(count($children)===0 && count($attributes)===0){
        $txt = $node->textContent;
        if($txt==="")
          $jsonArray=NULL;
        else
          $jsonArray=$txt;
      }

      $childrenByName = self::groupChildrenByName($children);

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
            $items[]=self::build_from_xml($child);
          }
          else if($child->nodeType ===XML_TEXT_NODE && $child->nodeValue ==="")
          {/* do not serialize this*/}
          else
            $items[]=$child->nodeValue;
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

      /* Zero-sized arrays are in fact NULLs */
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

    private static function groupChildrenByName($kinder){
      $childrenByName=array();
      foreach($kinder as $child){
        $name=$child->nodeName;

        if(!isset($childrenByName[$name]))
          $childrenByName[$name]=array();

        $childrenByName[$name][]=$child;
      }

      return $childrenByName;
    }

    private static function escapeXml($xml_string){
      return str_replace(array('&', '<'), array('&amp;', '&lt;'), $xml_string);
    }

    private static function hasOnlyNumericKeys(array $value){
      $hasOnlyNumericKeys = true;
      $i = 0;
      foreach($value as $k=>$v)
        if($k!==$i++)
          $hasOnlyNumericKeys = false;

          return $hasOnlyNumericKeys;
    }

    private static function startsWith($symbol, $aString){
      return substr($aString, 0, strlen($symbol)) === $symbol;
    }

    private static function isNamespaceAttribute($attrName){
      return substr($attrName,0,strlen("@xmlns"))==="@xmlns";
    }

}

