<?php

namespace Shtumi\UsefulBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\HttpFoundation\Response;

class DependentFilteredEntityController extends Controller
{

    public function getOptionsAction()
    {

        $em = $this->get('doctrine')->getEntityManager();
        $request = $this->getRequest();
        $translator = $this->get('translator');

        $entity_alias = $request->get('entity_alias');
        $parent_id    = $request->get('parent_id');
        $empty_value  = $request->get('empty_value');

        $entities = $this->get('service_container')->getParameter('shtumi.dependent_filtered_entities');
        $entity_inf = $entities[$entity_alias];

        if (false === $this->get('security.context')->isGranted( $entity_inf['role'] )) {
            throw new AccessDeniedException();
        }

        $qb = $this->getDoctrine()
                ->getRepository($entity_inf['class'])
                ->createQueryBuilder('e');
        $parent_property = explode(',', $entity_inf['parent_property']);
        foreach ($parent_property as $pp) {
            $qb->andWhere('e.' . $pp . ' = :parent_id_' . $pp);
            if (is_array($parent_id)) {
               $qb->setParameter('parent_id_' . $pp, $parent_id[$pp]);
                continue;
            }
            $qb->setParameter('parent_id_' . $pp, $parent_id);
        }

        $qb->orderBy('e.' . $entity_inf['order_property'], $entity_inf['order_direction']);

        if (null !== $entity_inf['callback']) {
            $repository = $qb->getEntityManager()->getRepository($entity_inf['class']);
            
            if (!method_exists($repository, $entity_inf['callback'])) {
                throw new \InvalidArgumentException(sprintf('Callback function "%s" in Repository "%s" does not exist.', $entity_inf['callback'], get_class($repository)));
            }
            
            $repository->$entity_inf['callback']($qb);
        }
        $sql = $qb->getQuery();
        $results = $sql->getResult();

        if (empty($results)) {
            return new Response('<option value="">' . $translator->trans($entity_inf['no_result_msg']) . '</option>');
        }

        $html = '';
        if ($empty_value)
            $html .= '<option value="">' . $translator->trans($empty_value) . '</option>';

        $getter =  $this->getGetterName($entity_inf['property']);

        foreach($results as $result)
        {
            if ($entity_inf['property'])
                $res = $result->$getter();
            else $res = (string)$result;

            $html = $html . sprintf("<option value=\"%d\">%s</option>",$result->getId(), $res);
        }

        return new Response($html);

    }

    private function getGetterName($property)
    {
        $name = "get";
        $name .= mb_strtoupper($property[0]) . substr($property, 1);

        while (($pos = strpos($name, '_')) !== false){
            $name = substr($name, 0, $pos) . mb_strtoupper(substr($name, $pos+1, 1)) . substr($name, $pos+2);
        }

        return $name;

    }
}
