<?php
/*
 * Copyright (C) 2015 Michael Herold <quabla@hemio.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace herold\libinternetx;

/**
 * Description of HandleUpdate
 *
 * @author Michael Herold <quabla@hemio.de>
 */
class HandleUpdate extends Task
{

    public static function code()
    {
        return '0302';
    }

    /**
     *
     * @param array $data
     * @param string $replyToEmail
     */
    public function addHandle(array $data, $replyToEmail)
    {
        $this->task->appendChild(new \DOMElement('reply_to', $replyToEmail));
        $handle = $this->task->appendChild(new \DOMElement('handle'));

        Utils::fromArray($handle, $data);
    }
}
