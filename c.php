<?php 


function todo($message){
    throw new ErrorException("ERROR: not implemented " . $message);
}

enum TokenEnum : string
{
    case TokenName = 'TOKEN_NAME';
    case TokenOParan = 'TOKEN_OPARAN';
    case TokenCParan = 'TOKEN_CPARAN';
    case TokenOCurly = 'TOKEN_OCURLY';
    case TokenCCurly= 'TOKEN_CCURLY';
    case TokenSemiColon = 'TOKEN_SEMICOLON';
    case TokenNumber= 'TOKEN_NUMBER';
    case TokenString = 'TOKEN_STRING';
    case TokenReturn = 'TOKEN_RETURN';
}

class Token {
    public TokenEnum $type;
    public string $value;
    public Loc $loc;

    public function __construct(Loc $loc,TokenEnum $type,string $value) {
        $this->loc = $loc;
        $this->type = $type;
        $this->value = $value;
    }
}


class Loc {
    public string $file_path;
    public int $row;
    public int $col;

    public function __construct(string $file_path,int  $row, int $col) {
        $this->file_path = $file_path;
        $this->row = $row;
        $this->col = $col;
    }

    public function display(): string {
        return sprintf("%s:%d:%d", $this->file_path, $this->row + 1, $this->col + 1);
    }
}


class Lexer {
    public string $source;
    public int $cur;
    public int $bol;
    public int $row;
    public string $file_path;
    public $literal_tokens = array( 
        "(" => TokenEnum::TokenOParan,
        ")" => TokenEnum::TokenCParan,
        "{" => TokenEnum::TokenOCurly,
        "}" => TokenEnum::TokenCCurly,
        ";" => TokenEnum::TokenSemiColon,
    );

    function __construct(string $source, string $file_path){
        $this->source = $source;
        $this->file_path = $file_path;
        $this->cur = 0;
        $this->bol = 0;
        $this->row = 0;
    }

    private function is_eof(): bool {
        return !$this->has_char();
    }

    private function has_char(): bool {
        return $this->cur < strlen($this->source ?? '');
    }

    private function drop_line(): void {
        while ($this->has_char() && $this->source[$this->cur] !== "\n") {
            $this->chop_char();
        }
        // stops at new line and drop the new line char as well
        if ($this->has_char()) {
            $this->chop_char();
        }
    }

    private function trim_left(): void {
        while ($this->has_char() && ctype_space($this->source[$this->cur])) {
            $this->chop_char();
        }
    }

    private function chop_char():void {
        if($this->has_char()) {
            $x = $this->source[$this->cur];
            $this->cur += 1;

            if($x === "\n"){
                $this->bol = $this->cur;
                $this->row +=1;
            }
        }
    }

    public function loc(): Loc {
        return new Loc(file_path: $this->file_path, row: $this->row, col: $this->cur - $this->bol);
    }

    public function next_token(): Token | bool {
        $this->trim_left();

        if(str_starts_with(substr($this->source, $this->cur), "#")) {
            $this->drop_line();
            $this->trim_left();
        }

        if($this->is_eof()) {
            return false;
        }

        $loc = $this->loc();
        $first = $this->source[$this->cur];

        // int main() {
        // ^    ^
        // int
        //     main

        if(ctype_alpha(text: $first)){
            $index = $this->cur;

            while($this->has_char() && ctype_alnum($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = substr($this->source, $index, $this->cur - $index);
            return new Token(loc: $loc, type: TokenEnum::TokenName, value: $value );
        }


        if(isset($this->literal_tokens[$first])){
            $this->chop_char();
            $t = $this->literal_tokens[$first];
            return new Token(loc: $loc, type: $t, value: $first);
        }

        if($first === '"') {
            $this->chop_char();
            $start = $this->cur;

            while($this->has_char() && $this->source[$this->cur] !== '"') {
                $this->chop_char();
            }

            if($this->has_char()) {
                $value = substr($this->source, $start, $this->cur - $start);
                $this->chop_char();
                return new Token(loc: $loc, type: TokenEnum::TokenString, value: $value);
            }

            echo sprintf("%s: ERROR: unclosed string literal\n", $loc->display());
            return false;
        }

        if(ctype_digit($first)) {
            $start = $this->cur;

            while($this->has_char() && ctype_digit($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = (int) substr($this->source, $start, $this->cur - $start);
            return new Token(loc: $loc, type: TokenEnum::TokenNumber, value: $value);
        }

        todo("next_token()");
    }
}

function expect_token(Lexer $lexer, TokenEnum ...$types): Token | bool{
    $token = $lexer->next_token();

    if(!$token){
        echo sprintf("%s: ERROR: expected %s but got end of file\n", 
            $lexer->loc()->display(), join(" or", $types));
        return false;
    }


    foreach($types as &$type) {
        if ($token->type === $type) {
            return $token;
        }
    }

    echo sprintf("%s: ERROR: expected %s but got %s\n",
        $lexer->loc()->display(),
        join(" or ", array_map(fn($type) =>  $type->value, $types)),
        $token->type->value);
    return false;
}


define("TYPE_INT", "TYPE_INT");

class FuncallStmt {
    public Token $name;
    public $args;

    
    public function __construct(Token $name, $args) {
        $this->name = $name;
        $this->args = $args;
    }
}

class RetStmt {
    public $expr;

    public function __construct(string $expr) {
        $this->expr = $expr;
    }
}

class Func {
    public Token $name;
    public $body;

    public  function __construct(Token $name, $body){
        $this->name = $name;
        $this->body = $body;
    }
}

function parse_type(Lexer $lexer) {
    $return_type = expect_token($lexer, TokenEnum::TokenName);
    if ($return_type->value !== "int") {
        echo sprintf("%s: ERROR: unexpected type %s", 
            $return_type->loc->display(),
            $return_type->value);
        return false;
    }
    return TYPE_INT;
}

function parse_arglist(Lexer $lexer) {
    if (!expect_token($lexer, TokenEnum::TokenOParan)) return false;
    $arglist = array();

    while(true){
        $expr = expect_token($lexer, TokenEnum::TokenString, TokenEnum::TokenNumber, TokenEnum::TokenCParan);
        if(!$expr) return false;
        if($expr->type === TokenEnum::TokenCParan) break;
        array_push($arglist, $expr->value);
    }

    return $arglist;
}

function parse_block(Lexer $lexer) {
    if (!expect_token($lexer, TokenEnum::TokenOCurly)) return false;
    $block = array();

    while(true){
        $name = expect_token($lexer, TokenEnum::TokenName, TokenEnum::TokenCCurly);
        if(!$name) return false;
        if($name->type === TokenEnum::TokenCCurly) break;

        if($name->value === "return") {
            $expr = expect_token($lexer, TokenEnum::TokenNumber);
            if(!$expr) return false;
            array_push($block, new RetStmt(expr: $expr->value));
        } else {
            $arglist = parse_arglist($lexer);
            if(!$arglist) return false;
            array_push($block, new FuncallStmt($name, $arglist));
        }

        if(!expect_token($lexer, TokenEnum::TokenSemiColon)) return false;
    }

    return $block;
}

function parse_function(Lexer $lexer){
    $return_type = parse_type($lexer);
    if($return_type) false;

    assert($return_type === TYPE_INT);

    $name = expect_token($lexer, TokenEnum::TokenName);
    if(!$name) return;

    // void main() {}
    if (!expect_token($lexer, TokenEnum::TokenOParan)) return false;
    if (!expect_token($lexer, TokenEnum::TokenCParan)) return false;

    $block = parse_block($lexer);

    return new Func($name, $block);
}

$source = file_get_contents("./main.c") or die;
$lexer = new Lexer(source: $source, file_path: "main.c");
$func = parse_function($lexer);


class Compiler {
    public Func $func;

    public function __construct(Func $func){
        $this->func = $func;
    }

    public function compile() : void {
        $this->generate_ARM64_apple_silicon();
    }

    private function error(): void {
        exit(0);
    }

    private function generate_python3(): void{
        foreach ($this->func->body as $stmt) {
            if($stmt instanceof FuncallStmt) {
                switch ($stmt->name->value) {
                    case 'printf':
                        echo sprintf("print(\"%s\")\n", join(", ", $stmt->args));
                        break;
                    default:
                        echo sprintf("%s: ERROR: unknown function %s\n",
                           $stmt->name->loc->display(),
                           $stmt->name->value); 
                        break;
                }
            }
        }
    }

    private function generate_ARM64_apple_silicon(): void {
        print(".global _start \n");
        print(".p2align 3     \n\n");
        print("_start: \n");
        $variables = array();

        foreach ($this->func->body as $stmt) {
            if($stmt instanceof RetStmt) {
                //print("    mov rax, 60\n");
                //print("    mov rdi, {$stmt->expr}\n");
                //print("    syscall\n");
                
                print("mov     X0, #0\n"); // Use 0 return code
                print("mov     X16, #1\n");     // Service command code 1 terminates this program
                print("svc     0    \n");    // Call MacOS to terminate the program
            }

            //         array_push($arglist, $expr->value);

            if($stmt instanceOf FuncallStmt){
                switch ($stmt->name->value) {
                    case 'printf':
                        $n = 'str_'.count($variables);
                        $var_len = join(", ", $stmt->args);

                        print("mov X0, #1  \n");
                        print("adr X1, {$n}\n");
                        print("mov X2, #13 \n");
                        print("mov X16, #4 \n");
                        print("svc 0       \n");

                        $variables[$n] = join(", ", $stmt->args);
                        break;
                    default:
                        echo sprintf("%s: ERROR: unknown function %s\n",
                           $stmt->name->loc->display(),
                           $stmt->name->value); 
                        break;
                }
            }
        }

        print("\n\n");

        foreach ($variables as $key => $value) {
            print("{$key}:      .ascii  \"{$value}\"\n");
        }
    }
}

$res = new Compiler($func);
$res->compile();
