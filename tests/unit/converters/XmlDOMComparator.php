<?php
use NGS\Converter\XmlConverter;

class XmlDOMComparator {

  private static $ignoreWhitespaceTextNodes = true;

  /**
   * Returns true if the two DOM subtrees are equal
   */
  public static function equals(\DOMNode $xml_lhs, \DOMNode $xml_rhs) {

    $xmlPaths_lhs = array ();
    $xmlPaths_rhs = array ();

    $pathUpToNode_lhs=array();
    $pathUpToNode_rhs=array();

    $xml_lhs->normalize();
    $xml_rhs->normalize();

    self::killAllEmptyTextNodes($xml_lhs);
    self::killAllEmptyTextNodes($xml_rhs);

//     print "===\n";
//     print "LHS Path:\n";
//     print "===\n";
    self::buildPaths ( $xmlPaths_lhs, $pathUpToNode_lhs, $xml_lhs);
//     print "===\n";
//     print "RHS Path:\n";
//     print "===\n";
    self::buildPaths ( $xmlPaths_rhs, $pathUpToNode_rhs, $xml_rhs);
//     print "===\n";

    $result = self::compareAllPaths ( $xmlPaths_lhs, $xmlPaths_rhs );

    return $result;
  }

  private static function buildPaths(&$allPaths, $pathUpToCurrentNode, \DOMNode $node) {
    if ($node -> hasChildNodes() ) {
      foreach ( $node->childNodes as $child ) {

        $pathUpUntilThisChild = $pathUpToCurrentNode;
        $pathUpUntilThisChild [] = $node;

        self::buildPaths ( $allPaths, $pathUpUntilThisChild, $child);
      }
    } else {

      if(!self::isEmptyOrWhitespaceTextNode($node))
        $pathUpToCurrentNode [] = $node;

      $allPaths [] = $pathUpToCurrentNode;

      //Debug output:
//       print "Path complete -> \n";
//       print "Path up till now:";
//       var_dump ( $pathUpToCurrentNode );
    }
  }

  /**
   * Compares all root-to-leaf paths of the two XML trees.
   *
   * For each path in lhs list find an equivalent path in the rhs list, and
   * remove it if it exists.
   *
   * If for every path in lhs a unique equivalent path in rhs is found, the
   * trees are considered equal.
   *
   * @param
   *          lhs
   *          The lhs XML tree paths (array of arrays)
   * @param
   *          rhs
   *          The rhs XML tree paths (array of arrays)
   * @return {@code true} if they are equal {@code false} otherwise
   */
  private static function compareAllPaths($lhs_path, $rhs_path) {

    $retval=TRUE;

    $lhs=$lhs_path;
    $rhs=$rhs_path;

     if (count ( $lhs ) != count ( $rhs )) {
       print_r ( "The number of root-to-leaf paths is not equal: " . count ( $lhs ) . " vs. " . count ( $rhs ) . "\n" );
       $retval=false;

       if(count($rhs)>count($lhs)){
         print " (reversing lhr/rhs for debugging) \n";
         $lhs=$rhs_path;
         $rhs=$lhs_path;
       }
     }

    foreach ( $lhs as $key_lhs => $lhsPath ) {
      $found = FALSE;
      foreach ( $rhs as $key => $rhsPath ) {
        if (self::nodeListsEqual ( $lhsPath, $rhsPath )) {
          $found = TRUE;
          unset ( $lhs [$key_lhs] );
          unset ( $rhs [$key] );

          break;
        }
      }
      if ($found === FALSE) {
        print_r ( "---\n" );
        print_r ( "No pair found for path:\n" );
        print_r ( "---\n" );
        var_dump ( $lhsPath );
        print_r ( "---\n" );
        $retval=FALSE;
      }
    }

    return $retval;
  }

  /**
   * Compares two lists of nodes.
   *
   * The lists are considered equal if their nodes are equal. Since they
   * represent paths in a tree, their ordering is relevant.
   *
   * @param
   *          lhs
   *          Array of nodes serialized into arrays
   * @param
   *          rhs
   *          Array of nodes serialized into arrays
   * @return true if the {@code lists} are equal, {@code false} otherwise
   */
  private static function nodeListsEqual($lhs, $rhs) {
    if (count ( $lhs ) != count ( $rhs ))
      return false;

    reset ( $lhs );
    reset ( $rhs );

    while ( current ( $lhs ) !== FALSE ) {
      $node1 = current ( $lhs );
      $node2 = current ( $rhs );

      if(self::nodesEqual($node1, $node2) === FALSE){
        return false;
      }

      next ( $lhs );
      next ( $rhs );
    }

    return true;
  }

  private static function nodesEqual(\DOMNode $node1, \DOMNode $node2){
    $attributes1 = $node1 -> attributes;
    $attributes2 = $node2 -> attributes;

    $children1 = $node1 -> childNodes;
    $children2 = $node2 -> childNodes;

    if(count($attributes1) !== count($attributes2)){
      print "Nodes don't have the same number of attributes.\n";
      return FALSE;
    }

    if(count($children1) !== count($children2)){
      print "Nodes don't have the same number of children.\n";
      return FALSE;
    }

    if(count($attributes1)>0){
      reset($attributes1);
      reset($attributes2);

      while(current($attributes1)){
        $attr1 = current($attributes1);
        $attr2 = current($attributes2);

        if($attr1 -> name !== $attr2 -> name)
          return false;

        if($attr1 -> value !== $attr2 -> value)
          return false;

        next($attributes1);
        next($attributes2);
      }
    }

    if(count($children1)>0){
      while(current($children1)){
        $child1 = current($children1);
        $child2 = current($children2);

        if($child1 -> nodeType != $child2 -> nodeType)
          return false;

        if($child1 -> nodeType !==XML_ELEMENT_NODE){
          if($child1 -> nodeName !== $child2 -> nodeName)
            return false;

          if($child1 -> nodeValue !== $child2 -> nodeValue)
            return false;
        }

        next($attributes1);
        next($attributes2);
      }
    }

    return TRUE;
  }

  private static function isEmptyOrWhiteSpaceTextNode(\DOMNode $node){
    return $node -> nodeType === XML_TEXT_NODE
      && (ctype_space($node -> nodeValue) || $node -> nodeValue === "");
  }

  private static function killAllEmptyTextNodes(\DOMNode &$node){
    if($node -> hasChildNodes()){
      $nodesToRemove = array();
      foreach ($node -> childNodes as $child){
        if(self::isEmptyOrWhiteSpaceTextNode($child)){
          $nodesToRemove[] = $child;
        }
        else
          self::killAllEmptyTextNodes($child);
      }

      foreach ($nodesToRemove as $nodeToRemove){
        $node -> removeChild($nodeToRemove);
      }
    }
  }

}
