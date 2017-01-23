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
    var $networkInterface;
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

    public function convertValues($values)
    {
        $interfaces = array();

        foreach($values as $value){
            array_push($interfaces, explode(':', $value)[1]);
        }

        return $interfaces;
    }

    /**
     * @Route("/{ipAddress}/interfaces", name="snmp_interfaces")
     */
    public function interfaceAction($ipAddress)
    {
        $values = snmp2_walk($ipAddress, 'public', '1.3.6.1.2.1.2.2.1.2', $this->timeout, 1);
        $interfaces = $this->convertValues($values);

        $values = snmp2_walk($ipAddress, 'public', '1.3.6.1.2.1.2.2.1.3', $this->timeout, 1);
        $types = $this->convertValues($values);

        $values = snmp2_walk($ipAddress, 'public', '1.3.6.1.2.1.2.2.1.8', $this->timeout, 1);
        $status = $this->convertValues($values);

        return $this->render('interface/show.html.twig', [
            'ip' => $ipAddress,
            'interfaces' => $interfaces,
            'types' => $types,
            'status' => $status

        ]);

    }


    /**
     * @Route("/snmp/{ipAddress}/{interface}", name="snmp_show")
     */
    public function showAction($ipAddress, $interface = '22')
    {
        $this->networkInterface = $interface;

        $this->calcValues($ipAddress, 20);

        $snmp = new OctetsInterface();
        $snmp->setIfOutOctects($this->lastOutUtilization);
        $snmp->setIfInOctects($this->lastInUtilization);
        $snmp->setBandWidth($this->bandWidth);
        $snmp->setIfSpeed(($this->lastIfSpeed));
        $snmp->setCreatedDate(new \DateTime());
        $snmp->setIpAddress($ipAddress);
        $snmp->setInterface($interface);


        $em = $this->getDoctrine()->getManager();
        $em->persist($snmp);
        $em->flush();

        $snmpList = $this->getDoctrine()->getRepository('AppBundle:OctetsInterface')
            ->findBy([
                'ipAddress' => $ipAddress,
                'interface' => $interface
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
                ['Day', 'In Octets (bps)'],
            ]);

            $values = $lineChart->getData()->getArrayToDataTable();

            foreach ($octets as $octet){
                array_push($values,
                    [$octet->getCreatedDate(), $octet->getIfInOctects()]
                );
            }

            $lineChart->getData()->setArrayToDataTable($values);

            $lineChart->getOptions()->getChart()
                ->setTitle('Input utilization in Network Interface');

            return $lineChart;
        }

        if($option == 'outoctets'){

            $lineChart->getData()->setArrayToDataTable([
                ['Day', 'Output Utilization (bps)'],
            ]);

            $values = $lineChart->getData()->getArrayToDataTable();

            foreach ($octets as $octet){
                array_push($values,
                    [$octet->getCreatedDate(), $octet->getIfOutOctects()]
                );
            }

            $lineChart->getData()->setArrayToDataTable($values);

            $lineChart->getOptions()->getChart()
                ->setTitle('Output utilization in the Network Interface');


            return $lineChart;
        }

        return null;
    }

    public function calcValues($ipAddress, $poolTime)
    {
        if ($this->lastIfInOctet == null && $this->lastIfOutOctet == null) {
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
        } else{
            $ifInOctet1 = $this->lastIfInOctet;
            $ifOutOctet1 = $this->lastIfOutOctet;
            $ifSpeed = $this->lastIfSpeed;
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

        if($ifSpeed > 0) {
/*            $this->lastInUtilization = ((($ifInOctect2 - $ifInOctet1) * 8 * 100) / ($poolTime * $ifSpeed)) / 1048576;
            $this->lastOutUtilization = ((($ifOutOctect2 - $ifOutOctet1) * 8 * 100) / ($poolTime * $ifSpeed)) / 1048576;*/
//            $this->lastInUtilization = ((($ifInOctect2 - $ifInOctet1)) / ($poolTime * $ifSpeed));
//            $this->lastOutUtilization = ((($ifOutOctect2 - $ifOutOctet1)) / ($poolTime * $ifSpeed));

            $this->lastInUtilization = ((($ifInOctect2 - $ifInOctet1) * 8 * 100) / ($poolTime * $ifSpeed));
            $this->lastOutUtilization = ((($ifOutOctect2 - $ifOutOctet1) * 8 * 100) / ($poolTime * $ifSpeed));
            //$this->lastIfSpeed = $ifSpeed / 1048576;
            $this->lastIfSpeed = $ifSpeed;

        } else{
            $this->lastInUtilization = 0;
            $this->lastOutUtilization = 0;
            //$this->lastIfSpeed = $ifSpeed / 1048576;
            $this->lastIfSpeed = $ifSpeed;
        }

        $this->lastIfInOctet = $ifInOctect2;
        $this->lastIfOutOctet = $ifOutOctect2;
    }

    public function calcNetworkBandwidth($ifOutOctects, $ifInOctets, $ifSpeed, $pollTime)
    {
        $total = ($ifOutOctects+$ifInOctets)*8*100;

        if($ifSpeed == 0) {
            $networkSpeed = 0;
        }
        else {
            $networkSpeed = $total/(($ifSpeed*$pollTime));
        }

        //return $networkSpeed/1048576;
        return $networkSpeed;

    }

    /**
     * @Route("/snmp/{ipAddress}/{interface}/new", name="snmp_new")
     * @Method("GET")
     */
    public function getOctectsAction($ipAddress, $interface = '22')
    {
        $this->networkInterface = $interface;

        $this->calcValues($ipAddress, 20);

        $snmp = new OctetsInterface();
        $snmp->setIfOutOctects($this->lastOutUtilization);
        $snmp->setIfInOctects($this->lastInUtilization);
        $snmp->setBandWidth($this->bandWidth);
        $snmp->setIfSpeed(($this->lastIfSpeed));
        $snmp->setCreatedDate(new \DateTime());
        $snmp->setIpAddress($ipAddress);
        $snmp->setInterface($interface);


        $em = $this->getDoctrine()->getManager();
        $em->persist($snmp);
        $em->flush();

        $snmpList = $this->getDoctrine()->getRepository('AppBundle:OctetsInterface')
            ->findBy([
                'ipAddress' => $ipAddress,
                'interface' => $interface
            ]);

        $lineChartInOctets = $this->createChart($snmpList, 'inoctets');
        $lineChartOutOctets = $this->createChart($snmpList, 'outoctets');

        return $this->render(':monitoringip:_content.html.twig', [
            'ip' => $ipAddress,
            'snmpList' => $snmpList,
            'interface' => $this->networkInterface,
            'lineChartInOctets' => $lineChartInOctets,
            'lineChartOutOctets' => $lineChartOutOctets
        ]);
    }

}