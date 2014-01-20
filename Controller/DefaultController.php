<?php
/**
 * @package Newscoop\NewsletterPluginBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\NewsletterPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
* Newsletter controller
*/
class DefaultController extends Controller
{

    /**
     * @Route("/newsletter-plugin/subscribe", name="newscoop_newsletter_plugin_subscribe")
     */
    public function subscribeAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $newsletterService = $this->container->get('newscoop_newsletter_plugin.service');
            $translator = $this->container->get('translator');
            if ($request->request->has('newsletter-lists')) {
                $listIds = $request->request->get('newsletter-lists');
                $type = $request->request->get('newsletter-type');
                if (count($listIds["ids"]) != 1) {
                    foreach ($listIds["ids"] as $value) {
                        $newsletterService->subscribeUser($value, $type);
                    }

                    return new JsonResponse(array(
                        'message' => $translator->trans('plugin.newsletter.msg.successfully'),
                        'status' => true,
                    ));
                } else {
                    return new JsonResponse($newsletterService->subscribeUser($listIds["ids"], $type));
                }
            }

            return new JsonResponse(array(
                'message' => $translator->trans('plugin.newsletter.msg.selectone'),
                'status' => false
            ));
        }
    }
    /**
     * @Route("/newsletter-plugin/subscribe-public", name="newscoop_newsletter_plugin_subscribepublic")
     */
    public function subscribePublicAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $newsletterService = $this->container->get('newscoop_newsletter_plugin.service');
            if ($request->request->has('newsletter-lists-public')) {
                $listIds = $request->request->get('newsletter-lists-public');
                if (count($listIds["ids"]) != 1) {
                    foreach ($listIds["ids"] as $value) {
                        $newsletterService->subscribePublic($value, $type);
                    }

                    return new JsonResponse(array(
                        'message' => $translator->trans('plugin.newsletter.msg.successfully'),
                        'status' => true,
                    ));
                } else {
                    return new JsonResponse($this->subscribePublic($listIds["ids"], $type));
                }
            }

            return new JsonResponse(array(
                'message' => $translator->trans('plugin.newsletter.msg.selectone'),
                'status' => false
            ));
        }
    }

    /**
     * @Route("/newsletter-plugin/unsubscribe", name="newscoop_newsletter_plugin_unsubscribe")
     */
    public function unsubscribeAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $user = $this->container->get('user')->getCurrentUser();
            if ($user) {
                $newsletterService = $this->container->get('newscoop_newsletter_plugin.service');
                $email = $request->request->get('email');
                $listId = $request->request->get('listId');

                return $newsletterService->unsubscribe($email, $listId);
            }
        }
    }
}
