# Back redirect com roleta / raspadinha

Quando a pessoa aperta **voltar** na página de vendas, em vez de cair numa página de desconto ela cai numa
roleta (ou raspadinha) e ganha um prêmio: mais bônus pelo mesmo preço, ou mais bônus + desconto.

## Arquivos

| Arquivo | O que é |
|---|---|
| `index.html` | A página da roleta/raspadinha (arquivo único, sem dependências) |
| `backredirect.js` | Script para colar na página de vendas |

## Como configurar (5 passos)

### 1. Crie os checkouts de cada prêmio na Kash Pay
Um checkout para cada prêmio da roleta. Exemplo com os prêmios que já vêm configurados:

| Prêmio | Checkout |
|---|---|
| +3 bônus, mesmo preço | oferta normal + 3 bônus entregues no acesso |
| +3 bônus e 20% OFF | mesma entrega, preço com 20% de desconto |
| +5 bônus e 30% OFF | 5 bônus, preço com 30% de desconto |

E guarde também o link do checkout **normal**: ele é usado quando o tempo de reserva do prêmio acaba.

### 2. Edite o bloco `CONFIG` no topo do `<script>` do `index.html`
- `modo`: `"roleta"` ou `"raspadinha"`
- `produto`, `garantiaDias`, `minutosReserva`
- `checkoutPadrao`: link do checkout normal
- `premios`: título, bônus, preço, link de checkout e **peso** (a chance) de cada prêmio
- `textos`: todos os textos da página (já em espanhol)

### 3. Suba o `index.html` no seu domínio
Exemplo: `https://seudominio.com/raspadinha/`. Pode ser na mesma hospedagem da página de vendas.

### 4. Cole o back redirect na página de vendas, antes do `</body>`
```html
<script src="https://seudominio.com/raspadinha/backredirect.js"
        data-destino="https://seudominio.com/raspadinha/"></script>
```
Se o seu checkout (Kash Pay) tiver um campo de "back redirect" próprio, coloque lá o link da página também,
para pegar quem volta do checkout.

### 5. Teste
- Abra `https://seudominio.com/raspadinha/?reset=1` para jogar de novo (o `reset=1` apaga o prêmio salvo).
- `?modo=raspadinha` ou `?modo=roleta` troca o modo pela URL, então dá pra fazer teste A/B sem duplicar a página.

## VSL na VTurb (back redirect só depois do pitch)

Use o `backredirect-vturb.js` no lugar do `backredirect.js`. Ele só liga o redirect quando a pessoa chega no
pitch da VSL que está assistindo, e funciona com teste A/B (descobre qual VSL está tocando pela duração do vídeo).
Suba o arquivo na mesma pasta da raspadinha e cole na página de vendas, antes do `</body>`:

```html
<script>
window.BR_CONFIG = {
  destino: "https://seudominio.com/raspadinha/",
  vsls: [
    { pitch: "45:00", duracao: "" },   // VSL A
    { pitch: "54:55", duracao: "" },   // VSL B
  ],
};
</script>
<script src="https://seudominio.com/raspadinha/backredirect-vturb.js"></script>
```

- `duracao` só é necessária se as duas VSLs tiverem mais de 54:55 no total.
- Teste rápido: abra a página de vendas com `?debugbr=1&pitchteste=10` e aparece uma caixinha mostrando o que o
  script está vendo; o pitch passa a ser aos 10 segundos.

## O que a página já faz sozinha

- **Repassa as UTMs** da página de vendas → roleta → checkout, para a Utmify atribuir a venda ao anúncio certo.
- **O prêmio é salvo**: se a pessoa recarregar a página, aparece o mesmo prêmio (ela não fica girando até tirar o maior).
- **Tempo de reserva de verdade**: quando o contador zera, o botão passa a levar para o checkout normal.
- **Back redirect compatível com o Chrome**: o redirecionamento só é ativado depois do primeiro toque na página
  (dar play na VSL já conta). Sem isso, o Chrome simplesmente ignora o redirect.
- **Pixel (opcional)**: preencha `pixelId` e a página dispara os eventos personalizados `JogouRoleta` /
  `RaspouRaspadinha` e `ResgatouPremio`, que dá pra usar como público de remarketing.

## Cuidados

- **Todo prêmio que aparece na roleta precisa ser real e ter chance de sair.** Não coloque fatia "de enfeite"
  que nunca cai: isso é propaganda enganosa e gera reclamação e chargeback, justamente o que você precisa baixar.
  Para controlar o custo, use os pesos (ex.: 70 / 25 / 5) em vez de prêmio falso.
- **Back redirect e a Meta**: a Meta pode considerar "segurar o voltar" uma experiência ruim na página.
  Como 94% da sua verba está numa conta só, teste primeiro numa campanha menor e acompanhe a qualidade da conta
  antes de colocar em todas as ofertas.
- Os bônus e preços que vêm no `CONFIG` são **exemplos**: troque pelos seus.
