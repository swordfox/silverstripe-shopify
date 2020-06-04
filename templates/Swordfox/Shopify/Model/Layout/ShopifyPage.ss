<div class="content-container unit size3of4 lastUnit">
    <article>
        <h1>$Title</h1>
        <div class="content">$Content</div>

        <% if $AllProducts %>
        <section class="content center unit size1of1 lastUnit">
            <% loop $AllProducts %>
            <% include Swordfox\\Shopify\\Product %>
            <% end_loop %>
        </section>

        <section class="content center unit size1of1 lastUnit">
            <% with $AllProducts %>
            <% include Swordfox\\Shopify\\Pagination %>
            <% end_with %>
        </section>
        <% end_if %>
    </article>
</div>