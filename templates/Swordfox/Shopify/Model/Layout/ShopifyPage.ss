<section class="grid-container">
    <header class="grid-x grid-padding-x">
        <div class="cell">
            <h3>$Title</h3>
            $Content
        </div>
    </header>

    <article class="grid-container">
        <% if $AllProducts %>
        <section class="grid-x grid-padding-x small-2 medium-up-3 large-up-4">
            <% loop $AllProducts %>
            <% include Swordfox\\Shopify\\Product %>
            <% end_loop %>
        </section>
        <% end_if %>
    </article>

    <% with $AllProducts %>
    <footer class="grid-x grid-padding-x">
        <div class="cell">
            <% include Swordfox\\Shopify\\Pagination %>
        </div>
    </footer>
    <% end_with %>
</section>