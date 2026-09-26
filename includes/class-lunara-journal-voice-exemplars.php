<?php
/**
 * Dalton's own Journal entries, used as the voice target for Dispatch.
 *
 * Rules and banned-phrase lists tell a model what not to do; they never show it
 * what Dalton sounds like on the page. These two published entries do. They are
 * stored here as the code-owned default for editorial.voice.exemplars and
 * compiled into the Dispatch prompt as full texts, not as fragments.
 *
 * Text is the published copy with only the page furniture removed: the video
 * embed and its "Watch below" cue, the internal POST DETAILS comment, and the
 * trailing &nbsp;. Accents are folded to ASCII because the prompt asks for
 * ASCII-only output ("Eric Andre").
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Journal_Voice_Exemplars {
    const MAX_EXEMPLARS   = 3;
    const MAX_TEXT_CHARS  = 12000;
    const MAX_TITLE_CHARS = 200;
    const MAX_NOTE_CHARS  = 400;

    /**
     * @return array<int,array{title:string,note:string,text:string}>
     */
    public static function defaults() {
        return array(
            array(
                'title' => 'Robert Eggers Made a Werewolf Movie in Middle English and I Have Never Been More In',
                'note'  => 'Lunarafilm.com Journal, June 29, 2026. The full single-story shape: the take is in the first sentence, the history arrives as a fan tells it, every fact is framed, there is a personal stake, and it ends on a real question.',
                'text'  => <<<'EGGERS'
The first trailer for <em>Werwulf</em> dropped this morning, and I've already watched it more times than is reasonable, because Robert Eggers has done the thing only Robert Eggers would do: he made a werewolf movie set in England around the year 1300, written in actual Middle English, and he's releasing it on Christmas Day. Merry Christmas. Here is a man turning into a beast in a language nobody has spoken in seven hundred years.

You have to understand what kind of commitment this is. Eggers is the guy who built a 17th-century farm by hand for <em>The Witch</em>, who shot <em>The Lighthouse</em> in clammy black-and-white in a near-square frame on a real lighthouse in a real Nova Scotia storm, who made <em>The Northman</em> a Viking revenge saga that took its own berserker mysticism with total seriousness, who turned <em>Nosferatu</em> into a genuinely upsetting period nightmare while everyone else was making their horror look like a streaming thumbnail. The man does not wink. He builds a world down to the dirt under the fingernails and then dares you to live in it. And now he has aimed all of that obsessive, no-shortcuts energy at the oldest, pulpiest monster in the bin -- the werewolf -- and decided the way to make it frightening again is to make it medieval, filthy, candlelit, and linguistically authentic to a degree that borders on insane.

And the look -- God, the look. The trailer has that thing only an Eggers film has, the one you could pick out of a lineup in a single frame: the tallow-light and the wet stone, fog that seems to have weight, faces that look pulled off a seven-hundred-year-old woodcut. There's a distinct element to the way this man's images sit on your eye, and you cannot fake it and nobody else has it. Werwulf already has it. He shot it on color film with Jarin Blaschke, his regular eye, then ran the whole thing through an orthochromatic post-process that wrecks every skin tone and lends it the grain of old black-and-white -- a textured, filthy, hyper-detailed look engineered to make a miserable medieval world feel even nastier on your skin. You can see the whole grim, beautiful world of it in thirty seconds of cut-together footage. He told Empire he wants you to be able to smell it -- "smell the shit and mud in the street," in his words -- and that's the whole war he has always waged: horror that reaches you through the senses instead of sliding off the screen.

It's exactly the right call, and here's why. The werewolf has been a punchline for decades -- the rubber snouts, the CGI lunges, the guy in a varsity jacket with anger issues. Nobody has been scared by one since the practical-effects heyday. What the monster has always needed is a filmmaker willing to treat it with suffocating seriousness, to drag it back into the mud and the religious terror it came from, where a man becoming an animal isn't a special effect but a damnation. Eggers is the one director alive temperamentally incapable of doing it any other way.

And the period isn't a vibe; it's a fact too good to make up. Eggers set it around 1300 because that's the last gasp of werewolf lore in British history -- an English knight, by his telling, was ordered by the king to exterminate every wolf in the country to protect the wool trade, and he did the job so thoroughly that within a couple of centuries there were no wolves left in England at all, and so no werewolves left to be afraid of. He's planting his movie at the final moment the monster could still live in the English imagination. That's the kind of detail that gets me out of my seat. And he says he's shooting the creature itself like the first <em>Alien</em> -- kept in the dark, withheld, a mystery -- which is why the trailer gives you a single glimpse of Taylor-Johnson bone-cracking and foaming and then snatches it back. Slow burn. Tension over reveal. Thank God somebody still has the nerve.

And the company he's keeping makes it worse, in the best way. Aaron Taylor-Johnson is the Man, lured into the curse by an elder; Lily-Rose Depp is back after walking through the fire of <em>Nosferatu</em>; and the usual Eggers ghouls, Willem Dafoe and Ralph Ineson, are once again lending their faces to his shadows. Eggers calls Depp the heart of the film -- a grounded medieval mother a world away from her Nosferatu -- and these are all people who know the assignment, who've done the cold shoots and the impossible diction before and keep showing up the way character actors used to show up for the masters, because the work is the reward. What he says he's really digging at under all the fur is damage: some of the real men once tried as werewolves, he found, were carrying horrors done to them as boys. So it's a monster movie that's secretly about trauma. Of course it is.

So no, I can't be objective about this, and I'm not going to pretend otherwise. I'll be there opening night -- the way I was opening night for Nosferatu, for The Northman, and, yeah, for The Lighthouse, back when the room was a lot emptier and being an Eggers guy didn't come with a fan base. Not as many of you were there with him then, and that's fine. I've got the receipts. A new Eggers horror film is a holiday on my calendar, and this one is literally on the holiday: Christmas Day, a werewolf, Middle English, the most horrifying thing he's ever made by his own account -- and the man does not oversell. Subtitles and all, I'll be in that seat, ready to not understand a word and be terrified anyway.

Real question, though, because it's the wire the whole movie is walking: is shooting it in authentic Middle English the boldest swing in modern horror -- or the moment Eggers' commitment finally tips from genius into a bit he's doing for himself, and does it even matter as long as the dread lands?
EGGERS
            ),
            array(
                'title' => 'Paramount Let Street Fighter Be Unhinged. Thank God.',
                'note'  => 'Lunarafilm.com Journal, June 10, 2026. A trailer reaction: the verdict lands in paragraph one, the casting talk is specific enough to argue with, and the landing is short.',
                'text'  => <<<'STREETFIGHTER'
Paramount just dropped the first trailer for <em>Street Fighter</em>, and it looks like someone at the studio finally understood that the correct tone for a '90s fighting game movie is completely unhinged. This thing is neon-soaked, candy-colored, and aggressive in a way that suggests the adults in the room were told to leave. I mean that as a compliment.

Kitao Sakurai is directing. The <em>Bad Trip</em> guy. That turns out to be exactly the right pedigree for this. The Philippou brothers (<em>Talk to Me</em>) were originally attached, and I genuinely cannot imagine them making this movie. The Philippous make films that want to hurt you. They want you uncomfortable, destabilized, afraid of the cut. <em>Street Fighter</em> needs a director who wants to entertain you so aggressively it feels like an assault. A completely different frequency. Sakurai gets that. <em>Bad Trip</em> proved he can navigate chaos without losing the audience, and the trailer suggests he's dialed that instinct up to a budget that can actually support it. The pivot was the right call.

The cast is a deliberate kind of insane. Eric Andre, David Dastmalchian, Cody Rhodes, 50 Cent, Jason Momoa, Andrew Schulz, Orville Peck, Roman Reigns, Andrew Koji, Noah Centineo, Kyle Mooney, and Callina Liang. That's not an ensemble, that's a dare. Every name feels specifically chosen for this brand of stylized absurdity, and the fact that they cast two professional wrestlers tells you exactly how seriously they're taking the "tournament fighter" energy.

But let me be honest about what actually got me out of my chair: Andrew Koji as Ryu. If you watched <em>Warrior</em>, you already know this man can carry a franchise. If you only know him from <em>Snake Eyes</em>, you watched him make the mistake of giving a genuinely great performance inside a movie that didn't deserve one. Koji has been the best thing in everything he's been in and has somehow not gotten the vehicle that matches the talent. This could be it. Paired with Centineo as Ken, the martial artist and the pretty boy, that tension alone would have me in the theater. And then there's Callina Liang as Chun-Li. If you haven't seen Soderbergh's <em>Presence</em>, extremely underrated, criminally underseen, she's essentially the lead, the one being stalked by the thing in the house, and she's excellent in it. A damn good movie that not enough people showed up for. So she's got the goods. But I'm also just going to say it: based on the trailer, she is a stunningly beautiful human being, and when the first image of your Chun-Li stops you mid-scroll, the casting has done its job before the performance even starts.

Set in 1993 (smart, gives them permission to go full aesthetic), the plot is standard tournament fare: estranged fighters, mysterious recruiter, shadowy conspiracy, "GAME OVER" in the synopsis like it's a threat. None of that matters. What matters is whether the movie has the nerve to stay this loud for two hours, and whether Sakurai can keep the energy from tipping into exhaustion. The trailer says yes. October 16 will confirm it.

Early word is that it plays like a fan film with a massive Hollywood budget behind it. For this IP, after everything it's been through on screen, that might be the best possible outcome. The last thing <em>Street Fighter</em> needed was someone trying to make it respectable. It needed someone willing to let it be exactly what it is: trashy, stylized, and fully committed to the bit.

October 16. I'll be there.
STREETFIGHTER
            ),
        );
    }

    /**
     * Keep up to three well-formed exemplars. Titles and notes are plain text;
     * the body keeps only <em> (film titles), is folded to ASCII, and is capped.
     * Anything malformed is dropped rather than compiled half-formed.
     *
     * @param mixed $values Candidate exemplars.
     * @return array<int,array{title:string,note:string,text:string}>
     */
    public static function sanitize( $values ) {
        if ( ! is_array( $values ) ) {
            return array();
        }
        $out = array();
        foreach ( $values as $row ) {
            if ( count( $out ) >= self::MAX_EXEMPLARS ) {
                break;
            }
            if ( ! is_array( $row ) || ! isset( $row['title'], $row['text'] ) || ! is_scalar( $row['title'] ) || ! is_scalar( $row['text'] ) ) {
                continue;
            }
            $title = self::cap( self::ascii( self::plain( $row['title'] ) ), self::MAX_TITLE_CHARS );
            $note  = isset( $row['note'] ) && is_scalar( $row['note'] ) ? self::cap( self::ascii( self::plain( $row['note'] ) ), self::MAX_NOTE_CHARS ) : '';
            $text  = self::cap( self::body( $row['text'] ), self::MAX_TEXT_CHARS );
            if ( '' === $title || '' === $text ) {
                continue;
            }
            $out[] = array(
                'title' => $title,
                'note'  => $note,
                'text'  => $text,
            );
        }
        return $out;
    }

    private static function body( $value ) {
        $value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
        $value = str_replace( array( '&nbsp;', "\xC2\xA0" ), ' ', $value );
        $value = preg_replace( '/<!--.*?-->/s', '', $value );
        $value = preg_replace( '#<(/?)em\b[^>]*>#i', "\x01$1em\x02", $value );
        $value = strip_tags( $value );
        $value = str_replace( array( "\x01", "\x02" ), array( '<', '>' ), $value );
        $value = self::ascii( $value );
        $value = preg_replace( '/[ \t]+\n/', "\n", $value );
        $value = preg_replace( '/\n{3,}/', "\n\n", $value );
        return trim( $value );
    }

    private static function plain( $value ) {
        return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
    }

    /**
     * The prompt asks the model for straight quotes, two-hyphen dashes, and
     * ASCII-only copy, so the examples it imitates must follow the same rule.
     */
    private static function ascii( $value ) {
        $value = strtr( (string) $value, array(
            "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'", "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
            "\xE2\x80\x94" => '--', "\xE2\x80\x93" => '--', "\xE2\x80\xA6" => '...',
        ) );
        if ( function_exists( 'remove_accents' ) ) {
            $value = remove_accents( $value );
        }
        return preg_replace( '/[^\x09\x0A\x0D\x20-\x7E]/', '', $value );
    }

    private static function cap( $value, $max ) {
        $value = (string) $value;
        return strlen( $value ) > $max ? rtrim( substr( $value, 0, $max ) ) : $value;
    }
}
