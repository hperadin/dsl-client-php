<?php
use NGS\Converter\XmlConverter;

/**
 * TODO: Not a test
 * TODO: Docs
 * Utility method for comparing two Xml arrays
 *
 *
 */
class XmlArrayComparator {

  /**
   * Returns true if the two XML arrays are equal
   */
  public static function equals($xml_lhs, $xml_rhs ){
    // TODO: normalize namespace attributes, currently there are problems with those
    // TODO: perhaps collapse text and cdata nodes for comparison

    $xmlPaths_lhs=array();
    $xmlPaths_rhs=array();

    self::buildPaths($xmlPaths_lhs, array(), $xml_lhs);
    self::buildPaths($xmlPaths_rhs, array(), $xml_rhs);

     print "paths built:\n";
//     var_dump($xmlPaths_lhs);
//     print "---\n";
//    var_dump($xmlPaths_rhs);

    $result = self::compareAllPaths($xmlPaths_lhs, $xmlPaths_rhs);
    var_dump($result);

    return $result;
  }

  private static function keyOrWhole($node){
    if (is_array($node))
      return key($node);
    else
      return $node;
  }

  private static function buildPaths(&$allPaths, $pathUpToNode, $node){
      if(is_array($node)){
        foreach($node as $child){
          self::buildPaths($allPaths, $pathUpToNode, $child);
        }
      }
      else{
        $pathUpToNode[]=$node;
        $allPaths[]=$pathUpToNode;
        $pathUpToNode[]=array();
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
   * @param lhs
   *          The lhs XML tree paths (array of arrays)
   * @param rhs
   *          The rhs XML tree paths (array of arrays)
   * @return {@code true} if they are equal {@code false} otherwise
   */
  private static function compareAllPaths($lhs, $rhs){
    if(count($lhs)!=count($rhs)){
      print_r("The arrays given are of different length:\n");
      print_r("$lhs\n");
      print_r("$rhs\n");
      return false;
    }

    foreach($lhs as $lhsPath){
      $found=false;
      foreach($rhs as $rhsPath){
        if(self::nodeListsEqual($lhsPath, $rhsPath)){
          $found=true;
          //unset($rhs[$keyR]);
          break;
        }
      }
      if($found === false){
        print_r("No pair found for array:\n");
        print_r($lhs);
        return false;
      }
    }

    return true;
  }

  /**
   * Compares two lists of nodes.
   *
   * The lists are considered equal if their nodes are equal. Since they
   * represent paths in a tree, their ordering is relevant.
   *
   * @param lhs
   *          Array of nodes serialized into arrays
   * @param rhs
   *          Array of nodes serialized into arrays
   * @return true if the {@code lists} are equal, {@code false} otherwise
   */
  private static function nodeListsEqual($lhs, $rhs) {

    if (count($lhs) != count($rhs))
      return false;

    reset($lhs);
    reset($rhs);

    while(current($lhs)===TRUE){
      $node1=current($lhs);
      $node2=current($rhs);
      //if(self::nodesEqual($node1, $node2)===FALSE)
      if(($node1 === $node2) === FALSE)
        return false;
      next($lhs);
      next($rhs);
    }

    return true;
  }

  private static function nodesEqual($node1, $node2) {
    if(!isset($node1) & !isset($node2))
      return true;
    else if(!isset($node1) || !isset($node2)){
      return false;
    }
    else{
      return
      self::nodesHaveEqualNames($node1, $node2)
      && self::nodesHaveEqualNumberOfChildren($node1, $node2)
      && self::nodesHaveEqualValues($node1, $node2);
    }
  }

  private static function nodesHaveEqualNames($node1, $node2){
    return key($node1) === key($node2);
  }

  private static function nodesHaveEqualNumberOfChildren($node1, $node2){
    return count($node1) === count($node2);
  }

  private static function nodesHaveEqualValues($node1, $node2){
    return value($node1) === value($node2);
  }

}
