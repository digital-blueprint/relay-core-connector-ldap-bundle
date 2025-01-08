<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\LogicalNode;

class UndefinedLogicalNode extends LogicalNode
{
    public function getNodeType(): string
    {
        return 'undefined';
    }

    public function apply(array $rowData): bool
    {
        return true;
    }
}
