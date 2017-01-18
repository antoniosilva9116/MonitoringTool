<?php

namespace AppBundle\Controller;

use AppBundle\Form\SNMPForm;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\Material\LineChart;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use SNMP;
use AppBundle\Entity\OctetsInterface;
use Symfony\Component\Validator\Constraints\Date;

class SNMPController extends Controller
{
    var $timeout = 1000000;
    var $networkInterface = '22';
    var $lastInUtilization;
    var $lastOutUtilization;
    var $lastIfInOctet;
    var $lastIfOutOctet;
    var $lastIfSpeed;
    var $bandWidth;

    /**
     * @Route("/", name="homepage")
     */
    public function homepageAction()
    {
        $form = $this->createForm(SNMPForm::class);

        if($form->isSubmitted() && $form->isValid()){
            $form = $form->getData();

            $ipAddress = $form->getIpAddress();

            return $this->redirectToRoute('snmp_show', [
                'ipAddress' => $ipAddress
            ]);
        }

        return $this->render('main/homepage.html.twig',[
            'snmpForm' => $form->createView(),
        ]);
    }


    /**
     * @Route("/snmp/{ipAddress}", name="snmp_show")
     */
    public function showAction($ipAddress)
    {
        $this->calcValues($ipAddress, 10);

        $snmp = new OctetsInterface();
        $snmp->setIfOutOctects($this->lastIfOutOctet);
        $snmp->setIfInOctects($this->lastInUtilization);
        $snmp->setBandWidth($this->bandWidth);
        $snmp->setIfSpeed(($this->lastIfSpeed));
        $snmp->setCreatedDate(new \DateTime());
        $snmp->setIpAddress($ipAddress);

        $em = $this->getDoctrine()->getManager();
        $em->persist($snmp);
        $em->flush();

        $snmpList = $this->getDoctrine()->getRepository('AppBundle:OctetsInterface')
            ->findBy([
                'ipAddress' => $ipAddress
            ]);

        $lineChartInOctets = $this->createChart($snmpList, 'inoctets');
        $lineChartOutOctets = $this->createChart($snmpList, 'outoctets');


        return $this->render('monitoringip/show.html.twig', array(
            'ip' => $ipAddress,
            'snmpList' => $snmpList,
            'interface' => $this->networkInterface,
            'lineChartInOctets' => $lineChartInOctets,
            'lineChartOutOctets' => $lineChartOutOctets
        ));
    }

    public function createChart(array $octets, $option)
    {
        $lineChart = new LineChart();

        if($option == 'inoctets'){

            $lineChart->getData()->setArrayToDataTable([
                ['Day', 'In Octets (Mbps)'],
            ]);

            $values = $lineChart->getData()->getArrayToDataTable();

            foreach ($octets as $octet){
                array_push($values,
                    [$octet->getCreatedDate(), $octet->getIfInOctects()]
                );
            }

            $lineChart->getData()->setArrayToDataTable($values);

            $lineChart->getOptions()->getChart()
                ->setTitle('In octets in Network Interface');

            return $lineChart;
        }

        if($option == 'outoctets'){

            $lineChart->getData()->setArrayToDataTable([
                ['Day', 'Out Octets (Mbps)'],
            ]);

            $values = $lineChart->getData()->getArrayToDataTable();

            foreach ($octets as $octet){
                array_push($values,
                    [$octet->getCreatedDate(), $octet->getIfOutOctects()]
                );
            }

            $lineChart->getData()->setArrayToDataTable($values);

            $lineChart->getOptions()->getChart()
                ->setTitle('Out Octets in the Network Interface');


            return $lineChart;
        }

        return null;
    }

    public function calcValues($ipAddress, $poolTime)
    {
        if ($this->lastIfInOctet == null && $this->lastIfInOctet <= 0 &&
            $this->lastIfOutOctet == null && $this->lastIfOutOctet <= 0) {
            $ifInOctects = explode(':', snmp2_walk($ipAddress, 'public', '1.3.6.1.2.1.2.2.1.10.'
                . $this->networkInterface, $this->timeout, 1)[0]);
            $ifOutOctects = explode(':', snmp2_walk($ipAddress, 'public', '1.3.6.1.2.1.2.2.1.16.'
                . $this->networkInterface, $this->timeout, 1)[0]);
            $ifSpeed = explode(':', snmp2_walk($ipAddress, 'public', '.1.3.6.1.2.1.2.2.1.5.'
                . $this->networkInterface, $this->timeout, 1)[0]);

            $ifInOctet1 = intval($ifInOctects[1]);
            $ifOutOctet1 = intval($ifOutOctects[1]);
            $ifSpeed = intval($ifSpeed[1]);

            sleep($poolTime);
        }

        $ifInOctects = explode(':',snmp2_walk($ipAddress, 'public', '.1.3.6.1.2.1.2.2.1.10.'
            . $this->networkInterface, $this->timeout, 1)[0]);
        $ifOutOctects = explode(':',snmp2_walk($ipAddress, 'public', '1.3.6.1.2.1.2.2.1.16.'
            . $this->networkInterface, $this->timeout, 1)[0]);

        $ifInOctect2 = intval($ifInOctects[1]);
        $ifOutOctect2 = intval($ifOutOctects[1]);


        $this->bandWidth = $this->calcNetworkBandwidth(($ifOutOctect2 - $ifOutOctet1),
            ($ifInOctect2 - $ifInOctet1),
            $ifSpeed,
            $poolTime
        );

        $this->lastInUtilization =  ((($ifInOctect2 - $ifInOctet1)*8)/($poolTime*$ifSpeed))/1048576;
        $this->lastOutUtilization = (((($ifOutOctect2 - $ifOutOctet1)*8)/($poolTime*$ifSpeed)))/1048576;
        $this->lastIfSpeed = $ifSpeed/1048576;

        $this->lastIfInOctet = $ifInOctect2;
        $this->lastIfOutOctet = $ifOutOctect2;
    }

    public function calcNetworkBandwidth($ifOutOctects, $ifInOctets, $ifSpeed, $pollTime)
    {
        $total = ($ifOutOctects+$ifInOctets);

        if($ifSpeed == 0) {
            $networkSpeed = 0;
        }
        else {
            $networkSpeed = $total/(($ifSpeed*$pollTime));
        }

        return $networkSpeed/1048576;
    }

    /**
     * @Route("/snmp/{ipAddress}/new", name="snmp_new")
     * @Method("GET")
     */
    public function getOctectsAction($ipAddress)
    {
        $this->calcValues($ipAddress, 10);

        $bandWidth = $this->calcNetworkBandwidth(
            $this->lastIfOutOctet,
            $this->lastInUtilization,
            $this->lastIfSpeed,
            10
        );

        $snmp = new OctetsInterface();
        $snmp->setIfOutOctects($this->lastIfOutOctet);
        $snmp->setIfInOctects($this->lastInUtilization);
        $snmp->setBandWidth($bandWidth);
        $snmp->setIfSpeed(($this->lastIfSpeed));
        $snmp->setCreatedDate(new \DateTime());
        $snmp->setIpAddress($ipAddress);

        $em = $this->getDoctrine()->getManager();
        $em->persist($snmp);
        $em->flush();

        $snmpList = $this->getDoctrine()->getRepository('AppBundle:OctetsInterface')
            ->findBy([
                'ipAddress' => $ipAddress
            ]);

        $lineChartInOctets = $this->createChart($snmpList, 'inoctets');
        $lineChartOutOctets = $this->createChart($snmpList, 'outoctets');

        return $this->render(':monitoringip:show.html.twig', [
            'ip' => $ipAddress,
            'snmpList' => $snmpList,
            'interface' => $this->networkInterface,
            'lineChartInOctets' => $lineChartInOctets,
            'lineChartOutOctets' => $lineChartOutOctets
        ]);
    }

}