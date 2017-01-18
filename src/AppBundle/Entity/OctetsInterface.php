<?php

/**
 * Created by PhpStorm.
 * User: anton
 * Date: 05/01/2017
 * Time: 18:02
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="octets_interface")
 */
class OctetsInterface
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $snmpID;

    /**
     * @ORM\Column(type="float")
     */
    private $ifInOctects;

    /**
     * @ORM\Column(type="float")
     */
    private $ifOutOctects;

    /**
     * @ORM\Column(type="float")
     */
    private $bandWidth;

    /**
     * @ORM\Column(type="float")
     */
    private $ifSpeed;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdDate;

    /**
     * @ORM\Column(type="string")
     */
    private $ipAddress;

    /**
     * @return mixed
     */
    public function getSnmpID()
    {
        return $this->snmpID;
    }

    /**
     * @param mixed $snmpID
     */
    public function setSnmpID($snmpID)
    {
        $this->snmpID = $snmpID;
    }

    /**
     * @return mixed
     */
    public function getIfInOctects()
    {
        return $this->ifInOctects;
    }

    /**
     * @param mixed $ifInOctects
     */
    public function setIfInOctects($ifInOctects)
    {
        $this->ifInOctects = $ifInOctects;
    }

    /**
     * @return mixed
     */
    public function getIfOutOctects()
    {
        return $this->ifOutOctects;
    }

    /**
     * @param mixed $ifOutOctects
     */
    public function setIfOutOctects($ifOutOctects)
    {
        $this->ifOutOctects = $ifOutOctects;
    }

    /**
     * @return mixed
     */
    public function getBandWidth()
    {
        return $this->bandWidth;
    }

    /**
     * @param mixed $bandWidth
     */
    public function setBandWidth($bandWidth)
    {
        $this->bandWidth = $bandWidth;
    }

    /**
     * @return mixed
     */
    public function getIfSpeed()
    {
        return $this->ifSpeed;
    }

    /**
     * @param mixed $ifSpeed
     */
    public function setIfSpeed($ifSpeed)
    {
        $this->ifSpeed = $ifSpeed;
    }

    /**
     * @return mixed
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
    }

    /**
     * @return mixed
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * @param mixed $ipAddress
     */
    public function setIpAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;
    }

}