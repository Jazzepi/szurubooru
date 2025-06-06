<div class='content-wrapper transparent' id='home'>
    <div class='messages'></div>
    <header>
        <img style="width:120pt;height:auto" src='img/800p.png'><img>
        <h1><%- ctx.name %></h1>
    </header>
    <% if (ctx.canListPosts) { %>
        <form class='horizontal'>
            <%= ctx.makeTextInput({name: 'search-text', placeholder: 'enter some tags'}) %>
            <input type='submit' value='Search'/>
            <span class=sep>or</span>
            <a href='<%- ctx.formatClientLink('posts') %>'>browse all posts</a>
        </form>
    <% } %>
    <div class='post-info-container'></div>
    <footer class='footer-container'></footer>
</div>
