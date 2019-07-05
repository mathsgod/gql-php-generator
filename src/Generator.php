<?

namespace GQL;

class Generator
{
    public $pdo = null;
    public $table = [];
    public $info = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->getInfo();
    }

    public function decamlize($string)
    {
        return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
    }

    public function getInfo()
    {
        foreach ($this->pdo->query("show tables") as $table) {
            $this->table[] = array_pop($table);
        }

        foreach ($this->table as $t) {
            $this->info[$t] = $this->getTableDesc($t);
        }
    }

    public function output(): string
    {
        $ret = "";

        $ret = "schema {
    query:Query
    subscription:Subscription
    mutation:Mutation
}\n\n";

        $ret .= "type query{\n";
        foreach ($this->table as $t) {
            $pri_key = $this->getTablePriKey($t);

            if ($pri_key) {
                $ret .= "\t$t($pri_key:Int!):$t\n";
            }
        }
        $ret .= "}\n\n";


        $ret .= "type subscription{\n";
        foreach ($this->table as $table) {
            $ret .= $this->getSubscription($table);
        }
        $ret .= "}\n\n";

        $ret .= "type mutation{\n";
        foreach ($this->table as $table) {
            $ret .= $this->getMutation($table);
        }
        $ret .= "}\n\n";


        foreach ($this->table as $table) {
            $ret .= $this->getType($table);
        }
        return $ret;
    }

    public function getSubscription(string $table): string
    {
        $str = "\tcreate$table(\n";
        foreach ($this->info[$table] as $column) {
            if ($column["Key"] == "PRI") continue;
            $str .= "\t\t" . $column["Field"] . ":" . $this->getDataType($column["Type"]) . $this->getNotNull($column) . "\n";
        }
        $str .= "\t):Boolean\n";

        return $str;
    }

    public function getMutation(string $table): string
    {
        $str = "\tupdate$table(\n";
        foreach ($this->info[$table] as $column) {
            $str .= "\t\t" . $column["Field"] . ":" . $this->getDataType($column["Type"]) . $this->getNotNull($column) . "\n";
        }
        $str .= "\t):Boolean\n";

        //del
        $str = "\tdelete$table(";
        $a = [];
        foreach ($this->info[$table] as $column) {
            if ($column["Key"] != "PRI") continue;
            $a[] = $column["Field"] . ":" . $this->getDataType($column["Type"]) . $this->getNotNull($column);
        }
        $str .= implode(",", $a);

        $str .= "):Boolean\n";


        return $str;
    }


    public function getTablePriKey(string $table): string
    {
        foreach ($this->info[$table] as $column) {
            if ($column["Key"] == "PRI") {
                return $column["Field"];
            }
        }
        return "";
    }

    public function getTableDesc(string $table)
    {
        $ret = [];
        foreach ($this->pdo->query("describe `$table`") as $t) {
            $ret[] = $t;
        }
        return $ret;
    }

    public function getType(string $table): string
    {
        $str = "type $table{\n";
        foreach ($this->info[$table] as $info) {
            $str .= "\t" . $info["Field"] . ":" . $this->getDataType($info["Type"]) . $this->getNotNull($info) . "\n";

            if ($info["Key"] == "PRI") {
                foreach ($this->findAllField($info["Field"]) as $t) {
                    $str .= "\t" . $t . ":[$t!]!\n";
                }
            } else {
                if ($t = $this->findTableByPri($info["Field"])) {
                    $str .= "\t" . $t . ":$t\n";
                }
            }
        }

        $str .= "}\n\n";
        return $str;
    }

    public function findTableByPri(string $field): string
    {

        foreach ($this->info as $table => $info) {
            $key_size = 0;
            foreach ($info as $inf) {
                if ($inf["Key"] == "PRI") {
                    $key_size++;
                }
            }

            if ($key_size != 1) {
                continue;
            }

            foreach ($info as $inf) {
                if ($inf["Key"] == "PRI" && $inf["Field"] == $field) {
                    return $table;
                }
            }
        }
        return "";
    }

    public function findAllField(string $field): array
    {
        $ret = [];
        foreach ($this->info as $table => $info) {
            foreach ($info as $inf) {
                if ($inf["Key"] == "PRI") continue;
                if ($inf["Field"] == $field) {
                    $ret[] = $table;
                    break;
                }
            }
        }
        return $ret;
    }

    public function getDataType(string $type): string
    {
        if (strpos($type, "tinyint") !== false) {
            return "Boolean";
        }

        if (strpos($type, "int") !== false) {
            return "Int";
        }
        if (strpos($type, "float") !== false) {
            return "Float";
        }

        return "String";
    }

    public function getNotNull(array $info)
    {
        if ($info["Null"] == "NO") {
            return "!";
        } else {
            return "";
        }
    }
}
